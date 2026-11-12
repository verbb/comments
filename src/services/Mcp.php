<?php
namespace verbb\comments\services;

use Craft;
use craft\db\Query;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\mcp\Access;
use verbb\comments\mcp\ToolDefinitions;
use verbb\comments\mcp\ToolException;

/** MCP operations use the same element lifecycle and notifications as CP moderation. */
class Mcp
{
    public function __construct(private readonly Access $access)
    {
    }

    public function tools(): array
    {
        $tools = [];
        foreach (ToolDefinitions::all() as $tool) {
            if (!$this->access->allows($tool['scope'])) {
                continue;
            }
            if ($tool['name'] === 'update_comment' && !$this->access->user->can('comments-edit')) {
                continue;
            }
            if ($tool['name'] === 'delete_comment' && !$this->access->user->can('comments-delete')) {
                continue;
            }
            unset($tool['scope']);
            $tools[] = $tool;
        }
        return $tools;
    }

    public function call(string $name, array $arguments): array
    {
        // Check the current allow-list on every call, not just during discovery.
        $tools = array_column($this->tools(), null, 'name');
        if (!isset($tools[$name])) {
            throw new ToolException('Unknown tool or permission denied. Use tools/list for permitted tools.');
        }
        ToolDefinitions::validate($tools[$name], $arguments);
        if (isset($arguments['siteId'])) {
            $this->access->requireSite($arguments['siteId']);
        }

        return match ($name) {
            'get_capabilities' => [
                'tools' => array_keys($tools),
                'siteIds' => $this->access->siteIds,
                'statuses' => ToolDefinitions::STATUSES,
                'maxPageSize' => 100,
                'maxCommentLength' => 20000,
                'includesPersonalContactData' => false,
                'permanentDeletion' => false,
            ],
            'list_comments' => $this->list($arguments),
            'get_comment' => ['comment' => $this->serialize($this->find($arguments), true)],
            'get_comment_stats' => $this->stats($arguments),
            default => $this->mutate($name, $arguments),
        };
    }

    private function query(array $arguments): CommentQuery
    {
        $query = Comment::find()
            ->siteId($arguments['siteId'] ?? $this->access->siteIds)
            ->status($arguments['status'] ?? null)
            ->trashed(false)
            ->orderBy(['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]);
        $query->populateRenderCache = false;
        foreach (['ownerId', 'userId'] as $key) {
            if (isset($arguments[$key])) {
                $query->$key($arguments[$key]);
            }
        }
        if (isset($arguments['flagged'])) {
            $query->isFlagged($arguments['flagged']);
        }
        if (isset($arguments['search'])) {
            // Yii binds and escapes the string, including SQL wildcard characters.
            $query->andWhere(['like', 'comments_comments.comment', $arguments['search']]);
        }
        return $query;
    }

    private function list(array $arguments): array
    {
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;
        $comments = $this->query($arguments)->offset($offset)->limit($limit + 1)->all();
        $hasMore = count($comments) > $limit;
        if ($hasMore) {
            array_pop($comments);
        }
        return [
            'comments' => array_map(fn(Comment $comment) => $this->serialize($comment), $comments),
            'limit' => $limit,
            'offset' => $offset,
            'nextOffset' => $hasMore ? $offset + $limit : null,
        ];
    }

    private function stats(array $arguments): array
    {
        $counts = [];
        foreach (ToolDefinitions::STATUSES as $status) {
            $counts[$status] = isset($arguments['status']) && $arguments['status'] !== $status
                ? 0 : (int)$this->query(array_merge($arguments, ['status' => $status]))->count();
        }
        return ['counts' => $counts, 'total' => array_sum($counts)];
    }

    private function find(array $arguments): Comment
    {
        $comment = $this->query(['siteId' => $arguments['siteId']])->id($arguments['commentId'])->one();
        if (!$comment) {
            throw new ToolException('Comment not found in the permitted site, or it has been deleted.');
        }
        return $comment;
    }

    private function mutate(string $name, array $arguments): array
    {
        // First resolve through the scoped query so unauthorized IDs are never locked.
        $this->find($arguments);
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // Craft supports MySQL/PostgreSQL. Serialize writers on the shared element row.
            $command = (new Query())->select(['id'])->from('{{%elements}}')->where(['id' => $arguments['commentId']])->createCommand();
            Craft::$app->getDb()->createCommand($command->getRawSql() . ' FOR UPDATE')->queryScalar();
            $comment = $this->find($arguments);
            if (!hash_equals($this->revision($comment), $arguments['expectedRevision'])) {
                throw new ToolException('Comment changed since it was read. Fetch get_comment again before retrying.');
            }

            if ($name === 'delete_comment') {
                if (!$comment->canDelete($this->access->user)) {
                    throw new ToolException('Permission denied for comment deletion.');
                }
                if (!Craft::$app->getElements()->deleteElement($comment, false)) {
                    throw new ToolException('Comment could not be deleted; a Craft event may have vetoed deletion.');
                }
                $result = ['commentId' => $comment->id, 'siteId' => $comment->siteId, 'deleted' => true, 'permanentlyDeleted' => false];
            } else {
                if ($name === 'moderate_comment') {
                    // Require trash permission both when trashing and when restoring a trashed status.
                    if (($arguments['status'] === Comment::STATUS_TRASHED || $comment->status === Comment::STATUS_TRASHED)
                        && !$this->access->user->can('comments-trash')) {
                        throw new ToolException('The comments-trash permission is required for this transition.');
                    }
                    $changed = $comment->status !== $arguments['status'];
                    $comment->status = $arguments['status'];
                } else {
                    if (!trim($arguments['comment'])) {
                        throw new ToolException('Comment text must not be blank.');
                    }
                    $changed = $comment->comment !== $arguments['comment'];
                    $comment->comment = $arguments['comment'];
                    if (!Comments::$plugin->getSecurity()->checkCommentLength($comment)) {
                        throw new ToolException('Comment exceeds the configured securityMaxLength.');
                    }
                }
                $comment->setScenario(Comment::SCENARIO_CP);
                if ($changed && !Craft::$app->getElements()->saveElement($comment, true, false)) {
                    // Field names give actionable context without returning validators' raw user data.
                    throw new ToolException('Comment validation failed for: ' . implode(', ', array_keys($comment->getErrors())) . '. Review it in the Craft control panel.');
                }
                $result = ['comment' => $this->serialize($comment), 'changed' => $changed];
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        Craft::info(json_encode(['tool' => $name, 'userId' => $this->access->user->id, 'commentId' => $comment->id, 'siteId' => $comment->siteId]), 'comments-mcp');
        return $result;
    }

    private function revision(Comment $comment): string
    {
        return hash('sha256', json_encode([
            $comment->id, $comment->siteId, $comment->dateUpdated?->format('Y-m-d\TH:i:s.uP'),
            $comment->status, $comment->comment, $comment->gifUrl,
        ], JSON_THROW_ON_ERROR));
    }

    private function serialize(Comment $comment, bool $detail = false): array
    {
        // Deliberate allow-list: never serialize the element wholesale (email, IP, user agent, custom fields).
        $data = [
            'id' => $comment->id,
            'siteId' => $comment->siteId,
            'ownerId' => $comment->ownerId,
            'ownerSiteId' => $comment->ownerSiteId,
            'userId' => $comment->userId,
            'guestName' => $comment->userId ? null : $comment->name,
            'comment' => $comment->comment,
            'gifUrl' => $comment->gifUrl,
            'status' => $comment->status,
            'level' => $comment->level,
            'commentDate' => $comment->commentDate?->format(DATE_ATOM),
            'dateUpdated' => $comment->dateUpdated?->format(DATE_ATOM),
            'revision' => $this->revision($comment),
        ];
        if ($detail) {
            $data['parentId'] = $comment->getParent()?->id;
            $data['flagCount'] = (int)(new Query())->from('{{%comments_flags}}')->where(['commentId' => $comment->id])->count();
        }
        return $data;
    }
}
