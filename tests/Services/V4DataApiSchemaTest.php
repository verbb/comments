<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\InteractionType;

/**
 * Every kind of data API response checked against the JSON Schema `@verbb/comments-core` publishes, so the
 * server and the package can't drift apart. The schema is validated with Opis, which the current test
 * matrix installs with Craft; the minimum matrix has none, so it skips there.
 */

const DATA_API_SCHEMA_ID = 'https://docs.verbb.io/comments/schema/data.json';

function dataApiSchemaErrors(mixed $data, string $definition): ?string
{
    $validator = new Opis\JsonSchema\Validator();
    $validator->setMaxErrors(5);
    $validator->resolver()->registerFile(DATA_API_SCHEMA_ID, dirname(__DIR__, 2) . '/packages/comments-core/schema/data.json');
    $result = $validator->validate(json_decode(json_encode($data, JSON_THROW_ON_ERROR)), DATA_API_SCHEMA_ID . '#/$defs/' . $definition);

    return $result->isValid() ? null : json_encode((new Opis\JsonSchema\Errors\ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT);
}

beforeEach(function() {
    if (!class_exists(Opis\JsonSchema\Validator::class) || !is_file(dirname(__DIR__, 2) . '/packages/comments-core/schema/data.json')) {
        $this->markTestSkipped('Needs opis/json-schema and the comments-core schema.');
    }

    $settings = Comments::$plugin->getSettings();
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->requireModeration = false;
    $settings->subscriptionTypes = ['conversation'];
    Comments::$plugin->getApi()->invalidateCaches();
    Comments::$plugin->getConversations()->clearCaches();
});

it('matches the published JSON Schema for every read, write and error', function() {
    $api = Comments::$plugin->getApi();
    $entry = CommentsFixtureFactory::entry('API schema owner');
    $author = CommentsFixtureFactory::user('apiSchemaAuthor');
    $root = dataApiPost($entry, 'Schema root', $author);
    $reply = dataApiPost($entry, 'Schema reply', null, $root);
    dataApiPost($entry, 'Schema waiting', $author, held: true);
    $removed = dataApiPost($entry, 'Schema removed parent', $author);
    dataApiPost($entry, 'Under a placeholder', null, $removed);
    Comments::$plugin->getModeration()->remove($removed, dataApiAdmin());
    Comments::$plugin->getInteractions()->set((int)$root->id, (int)Comments::$plugin->getIdentities()->createHistoricalGuest(null, null)->id, InteractionType::Upvote);
    $api->invalidateCaches();
    $ok = static fn(array $answer): array => ['apiVersion' => 1, 'status' => 'ok', ...$answer, 'replayed' => false];

    $responses = [
        'ThreadResponse' => [$api->thread($entry), $api->thread($entry, 'top', 1)],
        'RepliesResponse' => [$api->replies($root->uid)],
        'CommentResponse' => [$api->comment($reply->uid)],
        'StructuredDataResponse' => [['apiVersion' => 1, 'status' => 'ok', 'structuredData' => $api->structuredData($entry, ['includeAuthors' => true])]],
        'ProtectionResponse' => [['apiVersion' => 1, 'status' => 'ok', 'protection' => $api->protection($entry), 'csrf' => ['name' => 'CRAFT_CSRF_TOKEN', 'value' => 'token']]],
        'ErrorResponse' => [$api->errorData(dataApiError(fn() => $api->thread($entry, null, 99))), $api->errorData($api->errorFor(verbb\comments\exceptions\CommentValidationException::fromErrors(['comment' => ['Empty']])))],
    ];

    dataApiAs(null);
    $responses['ViewerResponse'][] = $api->viewer($entry, slim: true);
    $responses['SessionResponse'][] = $api->session(null, ['name' => 'CRAFT_CSRF_TOKEN', 'value' => 'token']);

    dataApiAs($author);
    $responses['ViewerResponse'][] = $api->viewer($entry, csrf: ['name' => 'CRAFT_CSRF_TOKEN', 'value' => 'token']);
    $responses['EditResponse'][] = $api->edit($root->uid);
    $responses['SessionResponse'][] = $api->session(null, ['name' => 'CRAFT_CSRF_TOKEN', 'value' => 'token']);
    $responses['SessionResponse'][] = $api->session($entry, ['name' => 'CRAFT_CSRF_TOKEN', 'value' => 'token']);
    $created = $api->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Schema created']]);
    $responses['CreateResponse'][] = $ok($created);
    $responses['UpdateResponse'][] = $ok($api->update(['uid' => $created['comment']['uid'], 'content' => ['format' => 'plain', 'text' => 'Schema updated']]));
    $responses['VoteResponse'][] = $ok($api->vote(['uid' => $reply->uid, 'vote' => 'upvote']));
    $responses['ReportResponse'][] = $ok($api->report(['uid' => $reply->uid, 'reason' => 'spam']));
    $responses['FollowResponse'][] = $ok($api->follow(['ownerId' => $entry->id, 'following' => true]));
    $responses['DeleteResponse'][] = $ok($api->delete(['uid' => $created['comment']['uid']]));

    dataApiAs(dataApiModerator());
    $responses['ViewerResponse'][] = $api->viewer($entry);
    $held = dataApiPost($entry, 'Schema held', $author, held: true);
    $responses['ModerateResponse'][] = $ok($api->moderate(['uid' => $held->uid, 'action' => 'approve']));
    $responses['ModerateResponse'][] = $ok($api->moderate(['uid' => $held->uid, 'action' => 'remove']));

    foreach ($responses as $definition => $answers) {
        foreach ($answers as $index => $answer) {
            expect(dataApiSchemaErrors($answer, $definition))->toBeNull("{$definition} #{$index}");
        }
    }
});
