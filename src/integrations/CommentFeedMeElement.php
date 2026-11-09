<?php
namespace verbb\comments\integrations;

use verbb\comments\elements\Comment as CommentElement;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\User as UserElement;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ElementHelper;
use craft\helpers\Json;

use yii\base\Event;

use DateTime;

use Cake\Utility\Hash;
use Carbon\Carbon;
use craft\feedme\base\Element;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\Plugin;
use craft\feedme\services\Process;

class CommentFeedMeElement extends Element
{
    // Properties
    // =========================================================================

    public static string $name = 'Comment';
    public static string $class = CommentElement::class;

    public $element = null;


    // Public Methods
    // =========================================================================

    public function getGroupsTemplate(): string
    {
        return 'comments/_integrations/feed-me/groups';
    }

    public function getColumnTemplate(): string
    {
        return 'comments/_integrations/feed-me/column';
    }

    public function getMappingTemplate(): string
    {
        return 'comments/_integrations/feed-me/map';
    }

    public function init(): void
    {
        parent::init();

        Event::on(Process::class, Process::EVENT_STEP_AFTER_ELEMENT_SAVE, function(FeedProcessEvent $event): void {
            if ($event->feed['elementType'] === CommentElement::class) {
                $this->_processNestedComments($event);
            }
        });
    }

    public function getGroups(): array
    {
        return [];
    }

    public function getQuery($settings, array $params = []): mixed
    {
        $query = CommentElement::find()
            ->status(null)
            ->siteId(Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);

        Craft::configure($query, $params);

        return $query;
    }

    public function setModel($settings): \craft\base\Element
    {
        $this->element = new CommentElement();

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        return $this->element;
    }


    // Protected Methods
    // =========================================================================

    protected function parseComment($feedData, $fieldInfo)
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        $this->element->comment = $value;

        return $value;
    }

    protected function parseCommentDate($feedData, $fieldInfo): ?DateTime
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $formatting = Hash::get($fieldInfo, 'options.match');

        return $this->parseDateAttribute($value, $formatting);
    }

    protected function parseParentId($feedData, $fieldInfo): ?int
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        $this->element->parentId = $value;

        return $value;
    }

    protected function parseOwnerId($feedData, $fieldInfo): ?int
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $match = Hash::get($fieldInfo, 'options.match');

        // Element lookups must have a value to match against
        if ($value === null || $value === '') {
            return null;
        }

        $elementId = null;

        $query = (new Query())
            ->select(['elements.id', 'elements_sites.elementId', 'elements_sites.title', 'elements_sites.slug', 'elements_sites.uri', 'elements_sites.content'])
            ->from(['{{%elements}} elements'])
            ->innerJoin('{{%elements_sites}} elements_sites', '[[elements_sites.elementId]] = [[elements.id]]')
            ->andWhere(['dateDeleted' => null]);

        // Check if we're query a column or a custom field
        if (in_array($match, ['title', 'slug', 'uri'])) {
            $query->andWhere(['=', $match, $value]);
        } else {
            // Swap the handle for the field layout UID. Pretty annoyingly complicated!
            foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
                $test = fn($layoutField) => (
                    $layoutField instanceof CustomField &&
                    $layoutField->getField()->handle === $match
                );

                if ($layout->isFieldIncluded($test)) {
                    $field = $layout->getField($test);

                    if ($field) {
                        $contentQuery = Craft::$app->getDb()->getQueryBuilder()->jsonContains('content', [$field->uid => $value]);

                        $query->andWhere($contentQuery);

                        break;
                    }
                }
            }
        }

        $result = $query->one();

        if ($result) {
            $elementId = $result['id'];
        }

        if ($elementId) {
            return $elementId;
        }

        return null;
    }

    protected function parseUserId($feedData, $fieldInfo): ?int
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $match = Hash::get($fieldInfo, 'options.match');
        $create = Hash::get($fieldInfo, 'options.create');

        // Element lookups must have a value to match against
        if ($value === null || $value === '') {
            return null;
        }

        if ($match === 'fullName') {
            $element = UserElement::findOne(['search' => $value, 'status' => null]);
        } else {
            $element = UserElement::find()
                ->status(null)
                ->andWhere(['=', $match, $value])
                ->one();
        }

        if ($element) {
            return $element->id;
        }

        // Check if we should create the element. But only if email is provided (for the moment)
        if ($create && $match === 'email') {
            $element = new UserElement();
            $element->username = $value;
            $element->email = $value;

            if (!Craft::$app->getElements()->saveElement($element)) {
                Plugin::error('Comment error: Could not create author - `{e}`.', ['e' => Json::encode($element->getErrors())]);
            } else {
                Plugin::info('Author `#{id}` added.', ['id' => $element->id]);
            }

            return $element->id;
        }

        return null;
    }


    // Private Methods
    // =========================================================================

    private function _processNestedComments($event): void
    {
        // Save the imported comment as the parent, we'll need it in a sec
        $parentId = $event->element->id;

        // Check if we're mapping a node to start looking for children.
        $childrenNode = Hash::get($event->feed, 'fieldMapping.children.node');

        if (!$childrenNode) {
            return;
        }

        // Check if there's any children data for the node we've just imported
        $expandedData = Hash::expand($event->feedData, '/');
        $childrenData = Hash::get($expandedData, $childrenNode, []);

        foreach ($childrenData as $childData) {
            // Prep the data, cutting the nested content to the top of the array
            $newFeedData = Hash::flatten($childData, '/');

            $processedElementIds = [];

            // Directly modify the field mapping data, because we're programatically adding
            // the `parentId`, which cannot be mapped.
            $event->feed['fieldMapping']['parentId'] = [
                'attribute' => true,
                'default' => $parentId,
            ];

            // Trigger the import for each child
            Plugin::$plugin->getProcess()->processFeed(-1, $event->feed, $processedElementIds, $newFeedData);
        }
    }
}
