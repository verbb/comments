<?php
namespace verbb\comments;

use verbb\comments\base\PluginTrait;
use verbb\comments\elements\Comment;
use verbb\comments\fields\CommentsField;
use verbb\comments\models\Settings;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\variables\CommentsVariableBehavior;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\models\Structure;
use craft\services\Elements;
use craft\services\Fields;
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

    public $schemaVersion = '1.0.1';
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

        // Only used on the /comments page, hook onto the 'cp.elements.element' hook to allow us to
        // modify the Title column for the element index table - we want something special.
        Craft::$app->view->hook('cp.elements.element', [Comment::class, 'getCommentElementTitleHtml']);
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('comments/settings'));
    }

    public function afterInstall()
    {
        // Comments are a Structure, which helps with hierarchy-goodness.
        // We only use a single structure for all our comments so store this at the plugin settings level
        if (!$this->getSettings()->structureId) {
            $structure = new Structure();

            Craft::$app->getStructures()->saveStructure($structure);

            // Update our plugin settings straight away!
            Craft::$app->getPlugins()->savePluginSettings($this, ['structureId' => $structure->id]);
        }
    }

    public function beforeUninstall(): bool
    {
        // Clean up structure
        if ($this->getSettings()->structureId) {
            Craft::$app->getStructures()->deleteStructureById($this->getSettings()->structureId);
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
                'comments/edit/<commentId:\d+>/<siteHandle>' => 'comments/comments/edit-template',
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

}
