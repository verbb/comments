<?php
namespace verbb\comments\integrations;

use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;

use Craft;
use craft\db\Query;
use craft\elements\User as UserElement;

use craft\feedme\base\Element;
use craft\feedme\Plugin;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\services\Process;

use Cake\Utility\Hash;
use yii\base\Event;

class CommentFeedMeElement extends Element
{
    // Properties
    // =========================================================================

    public static $name = 'Comment';
    public static $class = 'verbb\comments\elements\Comment';
    public $element;


    // Templates
    // =========================================================================

    public function getGroupsTemplate()
    {
        return 'comments/_integrations/feed-me/groups';
    }

    public function getColumnTemplate()
    {
        return 'comments/_integrations/feed-me/column';
    }

    public function getMappingTemplate()
    {
        return 'comments/_integrations/feed-me/map';
    }


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        Event::on(Process::class, Process::EVENT_STEP_AFTER_ELEMENT_SAVE, function(FeedProcessEvent $event) {
            if ($event->feed['elementType'] === CommentElement::class) {
                $this->_processNestedComments($event);
            }
        });
    }

    private function _processNestedComments($event)
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
            // the `newParentId`, which cannot be mapped.
            $event->feed['fieldMapping']['newParentId'] = [
                'attribute' => true,
                'default' => $parentId,
            ];

            // Trigger the import for each child
            Plugin::$plugin->getProcess()->processFeed(-1, $event->feed, $processedElementIds, $newFeedData);
        }
    }
    
    public function getGroups()
    {
        return [];
    }

    public function getQuery($settings, $params = [])
    {
        $query = CommentElement::find()
            ->anyStatus()
            ->siteId(Hash::get($settings, 'siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);
        
        Craft::configure($query, $params);
        
        return $query;
    }

    public function setModel($settings)
    {
        $this->element = new CommentElement();
        $this->element->structureId = Comments::getInstance()->getSettings()->structureId;

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

        $this->element->setComment($value);

        return $value;
    }

    protected function parseCommentDate($feedData, $fieldInfo)
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $formatting = Hash::get($fieldInfo, 'options.match');

        return $this->parseDateAttribute($value, $formatting);
    }

    protected function parseOwnerId($feedData, $fieldInfo)
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $match = Hash::get($fieldInfo, 'options.match');

        // Element lookups must have a value to match against
        if ($value === null || $value === '') {
            return null;
        }

        $elementId = null;

        // Because we can match on element attributes and custom fields, AND we're directly using SQL
        // queries in our `where` below, we need to check if we need a prefix for custom fields accessing
        // the content table.
        $columnName = $match;

        if (Craft::$app->getFields()->getFieldByHandle($match)) {
            $columnName = Craft::$app->getFields()->oldFieldColumnPrefix . $match;
        }

        $result = (new Query())
            ->select(['elements.id', 'elements_sites.elementId'])
            ->from(['{{%elements}} elements'])
            ->innerJoin('{{%elements_sites}} elements_sites', '[[elements_sites.elementId]] = [[elements.id]]')
            ->innerJoin('{{%content}} content', '[[content.elementId]] = [[elements.id]]')
            ->where(['=', $columnName, $value])
            ->andWhere(['dateDeleted' => null])
            ->one();

        if ($result) {
            $elementId = $result['id'];
        }

        if ($elementId) {
            return $elementId;
        }
    }

    protected function parseUserId($feedData, $fieldInfo)
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);
        $match = Hash::get($fieldInfo, 'options.match');
        $create = Hash::get($fieldInfo, 'options.create');

        // Element lookups must have a value to match against
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $match = 'elements.id';
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
                Plugin::error('Comment error: Could not create author - `{e}`.', ['e' => json_encode($element->getErrors())]);
            } else {
                Plugin::info('Author `#{id}` added.', ['id' => $element->id]);
            }

            return $element->id;
        }

        return null;
    }
}
