<?php
namespace verbb\comments\formfields;

use verbb\comments\Comments;
use verbb\comments\base\FormFieldType;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\fields\Assets;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\ConfigHelper;

use yii\base\InvalidArgumentException;

use verbb\editor\helpers\UploadSafety;

/**
 * Assets fields, as a file input. Files are uploaded first through `comments/api/upload` with the
 * field's handle, which applies Comments' upload rules and the field's own kinds and limits, and a
 * submission then sends the IDs it was given. Only a person's own uploads for the field, and the files a
 * comment already has, can be sent.
 *
 * A shown value is each file's address, name and kind, with an image's size, and the file names as text.
 */
class AssetsType extends FormFieldType
{
    // Static Methods
    // =========================================================================

    /**
     * The extensions an upload to `$field` may have: the field's file kinds, or anything Craft allows when
     * it has none, without the kinds Comments never takes.
     */
    public static function allowedExtensions(Assets $field): array
    {
        $extensions = array_map('strtolower', Craft::$app->getConfig()->getGeneral()->allowedFileExtensions);

        if ($field->restrictFiles && is_array($field->allowedKinds)) {
            $kinds = AssetsHelper::getFileKinds();
            $fromKinds = [];

            foreach ($field->allowedKinds as $kind) {
                array_push($fromKinds, ...array_map('strtolower', $kinds[$kind]['extensions'] ?? []));
            }

            $extensions = array_intersect($extensions, $fromKinds);
        }

        return array_values(array_unique(array_filter($extensions, static fn(string $extension): bool => !UploadSafety::isBlockedExtension($extension))));
    }

    /**
     * The largest file in bytes: Comments' **Maximum Attachment Size**, within Craft's `maxUploadFileSize`.
     */
    public static function maxSize(): int
    {
        $craftLimit = (int)ConfigHelper::sizeInBytes(Craft::$app->getConfig()->getGeneral()->maxUploadFileSize);
        $commentsLimit = max(1, (int)Comments::$plugin->getSettings()->attachmentMaxSize) * 1024 * 1024;

        return min($craftLimit, $commentsLimit);
    }


    // Public Methods
    // =========================================================================

    public function describe(FieldInterface $field, ?Comment $comment): array
    {
        $maxFiles = $field instanceof Assets && $field->maxRelations ? (int)$field->maxRelations : null;

        return [
            'type' => 'file',
            'multiple' => $maxFiles !== 1,
            'options' => [],
            'attributes' => [],
            'accept' => $field instanceof Assets ? array_map(static fn(string $extension): string => '.' . $extension, self::allowedExtensions($field)) : [],
            'maxFiles' => $maxFiles,
            'maxSize' => self::maxSize(),
        ];
    }

    public function normalizeInput(FieldInterface $field, mixed $submitted): mixed
    {
        if ($submitted === null || $submitted === '' || $submitted === []) {
            return [];
        }

        $ids = is_array($submitted) && array_is_list($submitted) ? $submitted : [$submitted];
        $normalized = [];

        foreach ($ids as $id) {
            $id = is_int($id) || (is_string($id) && ctype_digit($id)) ? (int)$id : 0;

            // Files are never sent with the comment itself, only the IDs `comments/api/upload` gave.
            if ($id < 1) {
                throw new InvalidArgumentException(Craft::t('comments', 'Upload files first, then send the IDs you were given.'));
            }

            $normalized[] = $id;
        }

        return array_values(array_unique($normalized));
    }

    public function display(FieldInterface $field, mixed $value): ?array
    {
        $files = array_map(fn(Asset $asset): array => $this->_file($asset), $this->_assets($value));

        if ($files === []) {
            return null;
        }

        return [
            'value' => $files,
            'text' => implode(', ', array_column($files, 'filename')),
        ];
    }

    /**
     * The files a comment has, with their IDs, so an edit can keep them by sending the IDs back.
     */
    public function inputValue(FieldInterface $field, mixed $value): mixed
    {
        return array_map(fn(Asset $asset): array => ['id' => (int)$asset->id, ...$this->_file($asset)], $this->_assets($value));
    }


    // Private Methods
    // =========================================================================

    private function _assets(mixed $value): array
    {
        if ($value instanceof ElementQueryInterface) {
            $value = $value->all();
        }

        if (!is_iterable($value)) {
            return [];
        }

        $assets = [];

        // Keyed by ID, because Craft can give a related asset once for each site it's on.
        foreach ($value as $asset) {
            if ($asset instanceof Asset) {
                $assets[(int)$asset->id] ??= $asset;
            }
        }

        return array_values($assets);
    }

    private function _file(Asset $asset): array
    {
        $file = [
            'url' => $asset->getUrl() ?: null,
            'filename' => $asset->getFilename(),
            'kind' => $asset->kind,
        ];

        if ($asset->kind === Asset::KIND_IMAGE && $asset->getWidth() && $asset->getHeight()) {
            $file['width'] = (int)$asset->getWidth();
            $file['height'] = (int)$asset->getHeight();
        }

        return $file;
    }
}
