<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\V3UpgradeLog;

use Craft;
use craft\db\Migration;
use craft\helpers\App;
use craft\helpers\FileHelper;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Comments 4 draws every thread with JavaScript, so **Template Folder Override** has nothing left to
 * override. A site that overrode the thread's templates gets each of its files listed in the Upgrade
 * Report, with a link to the guide that says where each change goes now, and the setting is dropped
 * from project config.
 *
 * The files are looked for in the folder the setting named, from project config or `config/comments.php`,
 * or in `templates/_comments`, the folder Comments 3's docs used, when the setting was empty. Files only
 * found there are reported more gently, as they may be a site's own form partials for the form actions.
 */
class m400000_000010_template_overrides extends Migration
{
    // Constants
    // =========================================================================

    public const NOTICE = 'templates.overridden';
    // Files found only in `templates/_comments`, which may be a site's own form partials rather than
    // overrides, so the report words them more gently.
    public const NOTICE_CONVENTIONAL = 'templates.conventionalFolder';
    public const GUIDE_URL = 'https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-comments-3#template-overrides';
    public const CONVENTIONAL_FOLDER = '_comments';

    // A templates folder is the site's own, so its walk is bounded: at most this many entries are looked
    // at, and this many template files listed.
    public const MAX_ENTRIES = 5000;
    public const MAX_FILES = 1000;


    // Static Methods
    // =========================================================================

    /**
     * The template files in an override folder, relative to the site's templates folder, such as
     * `_comments/_includes/comment.html`. A folder outside the templates folder lists nothing.
     */
    public static function overriddenFiles(string $folder): array
    {
        $root = realpath(Craft::$app->getPath()->getSiteTemplatesPath());
        $folder = trim(str_replace('\\', '/', $folder), '/');

        if ($root === false || $folder === '') {
            return [];
        }

        $path = realpath($root . DIRECTORY_SEPARATOR . $folder);

        if ($path === false || !is_dir($path) || !str_starts_with($path . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $extensions = array_map('strtolower', Craft::$app->getConfig()->getGeneral()->defaultTemplateExtensions);
        $files = [];
        $seen = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (++$seen > self::MAX_ENTRIES) {
                break;
            }

            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = FileHelper::normalizePath(substr($file->getPathname(), strlen($root) + 1), '/');
            }
        }

        // Sorted before cutting, so the files listed are the first by path, whatever order the folder
        // was read in. The walk itself is bounded by MAX_ENTRIES.
        sort($files);

        return array_slice($files, 0, self::MAX_FILES);
    }


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $folder = $this->_overrideFolder();
        $files = self::overriddenFiles($folder ?? self::CONVENTIONAL_FOLDER);

        // Each environment has its own templates and its own Upgrade Report, so this is recorded even
        // where project config is only being applied.
        if ($files !== []) {
            V3UpgradeLog::createTable($this);

            $log = new V3UpgradeLog($this->db);
            $log->addItems($folder !== null ? self::NOTICE : self::NOTICE_CONVENTIONAL, V3UpgradeLog::WARNING, $files);
            $log->flush();
        }

        $this->_removeSetting();

        return true;
    }

    public function safeDown(): bool
    {
        echo "m400000_000010_template_overrides cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    /**
     * The folder the site's setting named, with a config file winning over project config as it did for
     * Comments 3, or null when neither set one.
     */
    private function _overrideFolder(): ?string
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $file = Craft::$app->getConfig()->getConfigFromFile('comments');
        $candidates = [
            is_array($file) ? ($file['templateFolderOverride'] ?? null) : null,
            $projectConfig->get('plugins.comments.settings.templateFolderOverride'),
            $projectConfig->get('plugins.comments.settings.templateFolderOverride', true),
        ];

        foreach ($candidates as $value) {
            $value = is_string($value) ? trim((string)App::parseEnv($value)) : '';

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function _removeSetting(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $path = 'plugins.comments.settings.templateFolderOverride';

        // Applying YAML from the environment that ran this already leaves the setting out.
        if ($projectConfig->readOnly || version_compare($projectConfig->get('plugins.comments.schemaVersion', true) ?? '', '4.0.8', '>=')) {
            return;
        }

        $settings = $projectConfig->get('plugins.comments.settings') ?? [];

        if (!array_key_exists('templateFolderOverride', $settings)) {
            return;
        }

        // As in `m400000_000007_load_mode`, the key is given another value first, because Craft doesn't
        // save the removal of a value that compares as empty, such as `false`.
        $projectConfig->set($path, 'removed');
        $projectConfig->remove($path);
    }
}
