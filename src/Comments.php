<?php
namespace verbb\comments;

use verbb\comments\base\PluginTrait;
use verbb\comments\elements\Comment;
use verbb\comments\fields\CommentsField;
use verbb\comments\helpers\ProjectConfigData;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\queries\CommentQuery;
use verbb\comments\models\Settings;
use verbb\comments\services\CommentsService;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\variables\CommentsVariableBehavior;

use Craft;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\models\Structure;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\Plugins;
use craft\services\ProjectConfig;
use craft\services\SystemMessages;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;
use yii\web\User;

class Comments extends Plugin
{
    // Public Properties
    // =========================================================================

    public $schemaVersion = '1.1.4';
    public $hasCpSettings = true;
    public $hasCpSection = true;
    

    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerPermissions();
        $this->_registerEmailMessages();
        $this->_registerVariables();
        $this->_registerFieldTypes();
        $this->_registerElementTypes();
        $this->_registerGraphQl();
        $this->_registerCraftEventListeners();
        $this->_registerProjectConfigEventListeners();

        // Only used on the /comments page, hook onto the 'cp.elements.element' hook to allow us to
        // modify the Title column for the element index table - we want something special.
        Craft::$app->view->hook('cp.elements.element', [Comment::class, 'getCommentElementTitleHtml']);
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('comments', 'Comments');

        return $ret;
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('comments/settings'));
    }

    public function afterInstall()
    {
        // Comments are a Structure, which helps with hierarchy-goodness.
        // We only use a single structure for all our comments so store this at the plugin settings level
        if (!$this->getSettings()->structureUid) {
            $structure = new Structure();

            Craft::$app->getStructures()->saveStructure($structure);

            // We need to fetch the UID
            $structure = Craft::$app->getStructures()->getStructureById($structure->id);

            // Update our plugin settings straight away!
            Craft::$app->getPlugins()->savePluginSettings($this, ['structureUid' => $structure->uid]);
        }
    }

    public function beforeUninstall(): bool
    {
        // Clean up structure
        if ($this->getSettings()->structureUid) {
            Craft::$app->getStructures()->deleteStructureById($this->getSettings()->getStructureId());
        }

        return true;
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'comments/new' => 'comments/comments/edit-comment',
                'comments/<commentId:\d+>' => 'comments/comments/edit-comment',
                'comments/<commentId:\d+>/<siteHandle:{handle}>' => 'comments/comments/edit-comment',
                'comments/new/<siteHandle:{handle}>' => 'comments/comments/edit-comment',
                'comments/settings' => 'comments/base/settings',
            ]);
        });
    }

    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[Craft::t('comments', 'Comments')] = [
                'commentsEdit' => ['label' => Craft::t('comments', 'Edit other users\' comments')],
                'commentsTrash' => ['label' => Craft::t('comments', 'Trash other users\' comments')],
                'commentsDelete' => ['label' => Craft::t('comments', 'Delete comments')],
            ];
        });
    }

    private function _registerEmailMessages()
    {
        Event::on(SystemMessages::class, SystemMessages::EVENT_REGISTER_MESSAGES, function(RegisterEmailMessagesEvent $event) {
            $event->messages = array_merge($event->messages, [
                [
                    'key' => 'comments_author_notification',
                    'heading' => Craft::t('comments', 'comments_author_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_author_notification_subject'),
                    'body' => Craft::t('comments', 'comments_author_notification_body'),
                ], [
                    'key' => 'comments_reply_notification',
                    'heading' => Craft::t('comments', 'comments_reply_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_reply_notification_subject'),
                    'body' => Craft::t('comments', 'comments_reply_notification_body'),
                ], [
                    'key' => 'comments_subscriber_notification_element',
                    'heading' => Craft::t('comments', 'comments_subscriber_notification_element_heading'),
                    'subject' => Craft::t('comments', 'comments_subscriber_notification_element_subject'),
                    'body' => Craft::t('comments', 'comments_subscriber_notification_element_body'),
                ], [
                    'key' => 'comments_subscriber_notification_comment',
                    'heading' => Craft::t('comments', 'comments_subscriber_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_subscriber_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_subscriber_notification_comment_body'),
                ], [
                    'key' => 'comments_moderator_notification',
                    'heading' => Craft::t('comments', 'comments_moderator_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_moderator_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_moderator_notification_comment_body'),
                ], [
                    'key' => 'comments_moderator_approved_notification',
                    'heading' => Craft::t('comments', 'comments_moderator_approved_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_moderator_approved_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_moderator_approved_notification_comment_body'),
                ]
            ]);
        });
    }

    private function _registerVariables()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('comments', CommentsVariable::class);
        });
    }

    private function _registerFieldTypes()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CommentsField::class;
        });
    }

    private function _registerElementTypes()
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Comment::class;
        });
    }

    private function _registerGraphQl()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = CommentInterface::class;
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            $queries = CommentQuery::getQueries();
            
            foreach ($queries as $key => $value) {
                $event->queries[$key] = $value;
            }
        });
    }

    private function _registerCraftEventListeners()
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(Plugins::class, Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS, function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    $this->getComments()->saveFieldLayout();
                }
            });
        }
    }

    private function _registerProjectConfigEventListeners()
    {
        $projectConfigService = Craft::$app->getProjectConfig();
        $service = $this->getComments();

        $projectConfigService->onAdd(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onUpdate(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onRemove(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleDeletedFieldLayout']);

        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$service, 'pruneDeletedField']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['comments'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

}
