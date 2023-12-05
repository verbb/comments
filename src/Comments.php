<?php
namespace verbb\comments;

use verbb\comments\base\PluginTrait;
use verbb\comments\elements\Comment;
use verbb\comments\fields\CommentsField;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldLayoutElement;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\queries\CommentQuery;
use verbb\comments\gql\mutations\Comment as CommentMutations;
use verbb\comments\helpers\ProjectConfigData;
use verbb\comments\integrations\CommentFeedMeElement;
use verbb\comments\models\Settings;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\twigextensions\Extension;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\widgets\Comments as CommentsWidget;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\db\Table;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\PluginEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterGqlMutationsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\Structure;
use craft\records\StructureElement;
use craft\services\Dashboard;
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

use craft\feedme\events\RegisterFeedMeElementsEvent;
use craft\feedme\services\Elements as FeedMeElements;

class Comments extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.1.7';
    public string $minVersionRequired = '1.9.2';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerComponents();
        $this->_registerLogTarget();
        $this->_registerTwigExtensions();
        $this->_registerEmailMessages();
        $this->_registerVariables();
        $this->_registerFieldTypes();
        $this->_registerElementTypes();
        $this->_registerGraphQl();
        $this->_registerCraftEventListeners();
        $this->_registerProjectConfigEventListeners();
        $this->_checkDeprecations();
        $this->_registerFeedMeSupport();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerWidgets();
            $this->_registerFieldLayoutListener();
            $this->_registerTemplateHooks();
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->_registerResaveCommand();
        }

        if (Craft::$app->getEdition() === Craft::Pro) {
            $this->_registerPermissions();
        }
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('comments', 'Comments');

        return $ret;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('comments/settings'));
    }

    public function createAndStoreStructure(): ?Structure
    {
        $structure = null;

        // Try and find an existing comment elements' structure and use that
        $firstComment = Comment::find()->one();

        if ($firstComment) {
            $structureRecord = StructureElement::findOne([
                'elementId' => $firstComment->id,
            ]);

            if ($structureRecord) {
                $structure = Craft::$app->getStructures()->getStructureById($structureRecord->structureId);
            }
        }

        if (!$structure) {
            $structure = new Structure();

            Craft::$app->getStructures()->saveStructure($structure);

            // We need to fetch the UID
            $structure = Craft::$app->getStructures()->getStructureById($structure->id);
        }

        if ($structure) {
            // Update our plugin settings straight away!
            Craft::$app->getPlugins()->savePluginSettings($this, ['structureUid' => $structure->uid]);

            return $structure;
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new Extension);
    }

    private function _registerCpRoutes(): void
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

    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('comments', 'Comments'),
                'permissions' => [
                    'comments-edit' => ['label' => Craft::t('comments', 'Edit other users’ comments')],
                    'comments-trash' => ['label' => Craft::t('comments', 'Trash other users’ comments')],
                    'comments-delete' => ['label' => Craft::t('comments', 'Delete comments')],
                ],
            ];
        });
    }

    private function _registerEmailMessages(): void
    {
        Event::on(SystemMessages::class, SystemMessages::EVENT_REGISTER_MESSAGES, function(RegisterEmailMessagesEvent $event) {
            $event->messages = array_merge($event->messages, [
                [
                    'key' => 'comments_author_notification',
                    'heading' => Craft::t('comments', 'comments_author_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_author_notification_subject'),
                    'body' => Craft::t('comments', 'comments_author_notification_body'),
                ],
                [
                    'key' => 'comments_reply_notification',
                    'heading' => Craft::t('comments', 'comments_reply_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_reply_notification_subject'),
                    'body' => Craft::t('comments', 'comments_reply_notification_body'),
                ],
                [
                    'key' => 'comments_subscriber_notification_element',
                    'heading' => Craft::t('comments', 'comments_subscriber_notification_element_heading'),
                    'subject' => Craft::t('comments', 'comments_subscriber_notification_element_subject'),
                    'body' => Craft::t('comments', 'comments_subscriber_notification_element_body'),
                ],
                [
                    'key' => 'comments_subscriber_notification_comment',
                    'heading' => Craft::t('comments', 'comments_subscriber_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_subscriber_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_subscriber_notification_comment_body'),
                ],
                [
                    'key' => 'comments_moderator_notification',
                    'heading' => Craft::t('comments', 'comments_moderator_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_moderator_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_moderator_notification_comment_body'),
                ],
                [
                    'key' => 'comments_moderator_edit_notification',
                    'heading' => Craft::t('comments', 'comments_moderator_edit_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_moderator_edit_notification_subject'),
                    'body' => Craft::t('comments', 'comments_moderator_edit_notification_body'),
                ],
                [
                    'key' => 'comments_moderator_approved_notification',
                    'heading' => Craft::t('comments', 'comments_moderator_approved_notification_comment_heading'),
                    'subject' => Craft::t('comments', 'comments_moderator_approved_notification_comment_subject'),
                    'body' => Craft::t('comments', 'comments_moderator_approved_notification_comment_body'),
                ],
                [
                    'key' => 'comments_admin_notification',
                    'heading' => Craft::t('comments', 'comments_admin_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_admin_notification_subject'),
                    'body' => Craft::t('comments', 'comments_admin_notification_body'),
                ],
                [
                    'key' => 'comments_flag_notification',
                    'heading' => Craft::t('comments', 'comments_flag_notification_heading'),
                    'subject' => Craft::t('comments', 'comments_flag_notification_subject'),
                    'body' => Craft::t('comments', 'comments_flag_notification_body'),
                ],
            ]);
        });
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('comments', CommentsVariable::class);
        });
    }

    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CommentsField::class;
        });
    }

    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Comment::class;
        });
    }

    private function _registerCraftEventListeners(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(Plugins::class, Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS, function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    $fieldLayout = Craft::$app->getFields()->getLayoutByType(Comment::class);

                    // Ensure the field layout is created, if not.
                    if ($fieldLayout && !$fieldLayout->id) {
                        Db::insert(Table::FIELDLAYOUTS, ['type' => Comment::class]);
                    }

                    Comments::$plugin->getComments()->saveFieldLayout();
                }
            });
        }

        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN, function(PluginEvent $event) {
            // Comments are a Structure, which helps with hierarchy-goodness.
            // We only use a single structure for all our comments so store this at the plugin settings level
            if ($event->plugin === $this && !$this->getSettings()->structureUid) {
                $this->createAndStoreStructure();
            }
        });

        Event::on(Plugins::class, Plugins::EVENT_BEFORE_UNINSTALL_PLUGIN, function(PluginEvent $event) {
            // Clean up structure
            if ($event->plugin === $this && $this->getSettings()->structureUid) {
                Craft::$app->getStructures()->deleteStructureById($this->getSettings()->getStructureId());
            }
        });
    }

    private function _registerGraphQl(): void
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

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_MUTATIONS, function(RegisterGqlMutationsEvent $event) {
            $event->mutations = array_merge(
                $event->mutations,
                CommentMutations::getMutations()
            );
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $label = Craft::t('comments', 'Comments');

            $event->queries[$label] = [
                'comments:read' => ['label' => Craft::t('comments', 'View comments')],
            ];

            $event->mutations[$label] = [
                'comments:edit' => ['label' => Craft::t('comments', 'Create comments')],
                'comments:save' => ['label' => Craft::t('comments', 'Save comments')],
                'comments:delete' => ['label' => Craft::t('comments', 'Delete comments')],
            ];
        });
    }

    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();
        $service = $this->getComments();

        $projectConfigService->onAdd(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onUpdate(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleChangedFieldLayout'])
            ->onRemove(CommentsService::CONFIG_FIELDLAYOUT_KEY, [$service, 'handleDeletedFieldLayout']);

        $projectConfigService->onAdd('plugins.comments.settings', [$service, 'handleChangedPluginStructure'])
            ->onUpdate('plugins.comments.settings', [$service, 'handleChangedPluginStructure']);

        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$service, 'pruneDeletedField']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['comments'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function _registerFieldLayoutListener(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, function(DefineFieldLayoutFieldsEvent $event) {
            $fieldLayout = $event->sender;

            if ($fieldLayout->type == Comment::class) {
                $event->fields[] = CommentsFieldLayoutElement::class;
            }
        });
    }

    private function _checkDeprecations(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $settings = $this->getSettings();

        // Check for renamed settings
        $renamedSettings = [
            'allowAnonymous' => 'allowGuest',
            'allowAnonymousVoting' => 'allowGuestVoting',
            'allowAnonymousFlagging' => 'allowGuestFlagging',
            'securityBlacklist' => 'securitySpamlist',
        ];

        foreach ($renamedSettings as $old => $new) {
            if (property_exists($settings, $old) && isset($settings->$old)) {
                Craft::$app->getDeprecator()->log($old, "The {$old} config setting has been renamed to {$new}.");
                $settings[$new] = $settings[$old];
                unset($settings[$old]);
            }
        }

        $removedSettings = [
            'showCustomFields',
        ];

        foreach ($removedSettings as $setting) {
            if (property_exists($settings, $setting) && isset($settings->$setting)) {
                // Craft::$app->getDeprecator()->log($old, "The {$setting} config setting has been removed.");
                unset($settings[$setting]);
            }
        }
    }

    private function _registerFeedMeSupport(): void
    {
        if (class_exists(FeedMeElements::class)) {
            Event::on(FeedMeElements::class, FeedMeElements::EVENT_REGISTER_FEED_ME_ELEMENTS, function(RegisterFeedMeElementsEvent $event) {
                $event->elements[] = CommentFeedMeElement::class;
            });
        }
    }

    private function _registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CommentsWidget::class;
        });
    }

    private function _registerResaveCommand(): void
    {
        if (!Craft::$app instanceof ConsoleApplication) {
            return;
        }

        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, function(DefineConsoleActionsEvent $event) {
            $event->actions['comments-comments'] = [
                'action' => function(): int {
                    $controller = Craft::$app->controller;

                    return $controller->resaveElements(Comment::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Comments comments.',
            ];
        });
    }

    private function _registerTemplateHooks(): void
    {
        // Only used on the /comments page, hook onto the 'cp.elements.element' hook to allow us to
        // modify the Title column for the element index table - we want something special.
        Craft::$app->getView()->hook('cp.elements.element', [Comment::class, 'getCommentElementTitleHtml']);
    }
}
