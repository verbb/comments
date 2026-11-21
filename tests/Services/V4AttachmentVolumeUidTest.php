<?php
use verbb\comments\models\Settings;

/** A volume for these tests, removed again by `attachmentUidRemoveVolume()`. */
function attachmentUidVolume(): array
{
    $handle = 'attachmentUid' . bin2hex(random_bytes(4));
    $filesystem = new craft\fs\Local([
        'name' => $handle,
        'handle' => $handle,
        'path' => sys_get_temp_dir() . '/' . $handle,
        'hasUrls' => true,
        'url' => 'https://uploads.example.test/' . $handle . '/',
    ]);
    expect(Craft::$app->getFs()->saveFilesystem($filesystem))->toBeTrue();
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    expect(Craft::$app->getVolumes()->saveVolume($volume))->toBeTrue();

    return [$filesystem, $volume];
}

function attachmentUidRemoveVolume(array $created): void
{
    [$filesystem, $volume] = $created;
    Craft::$app->getVolumes()->deleteVolume($volume);
    Craft::$app->getFs()->removeFilesystem($filesystem);
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
}

/** Run a callback with `config/comments.php` holding these values, then remove the file. */
function attachmentUidWithConfigFile(array $values, callable $callback): mixed
{
    $path = Craft::$app->getConfig()->getConfigFilePath('comments');

    if (file_exists($path)) {
        throw new RuntimeException('The test app already has a config/comments.php.');
    }

    file_put_contents($path, '<?php return ' . var_export($values, true) . ';');

    try {
        return $callback();
    } finally {
        unlink($path);
    }
}

it('keeps the attachment volume by its UID, and uses its ID on this installation', function() {
    $created = attachmentUidVolume();
    [, $volume] = $created;

    try {
        $settings = new Settings(['allowAttachments' => true, 'attachmentVolume' => $volume->uid]);

        expect($settings->attachmentVolume)->toBe($volume->uid)
            ->and($settings->attachmentVolumeId)->toBe((int)$volume->id)
            ->and($settings->validate(['attachmentVolume', 'attachmentVolumeId']))->toBeTrue();

        // What Craft writes to project config for a save naming both: the UID, never this database's ID.
        $saved = $settings->toArray(['allowAttachments', 'attachmentVolume', 'attachmentVolumeId']);

        expect($saved)->toHaveKey('attachmentVolume', $volume->uid)
            ->and($saved)->not->toHaveKey('attachmentVolumeId');
    } finally {
        attachmentUidRemoveVolume($created);
    }
});

it('reads a development build’s saved volume ID as the volume’s UID', function() {
    $created = attachmentUidVolume();
    [, $volume] = $created;

    try {
        $settings = new Settings(['allowAttachments' => true, 'attachmentVolumeId' => (int)$volume->id]);

        expect($settings->attachmentVolume)->toBe($volume->uid)
            ->and($settings->attachmentVolumeId)->toBe((int)$volume->id)
            ->and($settings->toArray(['attachmentVolume', 'attachmentVolumeId']))->toBe(['attachmentVolume' => $volume->uid]);
    } finally {
        attachmentUidRemoveVolume($created);
    }
});

it('lets a config file’s volume ID win over a saved UID, unless the file sets the UID too', function() {
    $saved = attachmentUidVolume();
    $file = attachmentUidVolume();

    try {
        // Craft hands the model the saved settings with the file's values over them.
        attachmentUidWithConfigFile(['attachmentVolumeId' => (int)$file[1]->id], static function() use ($saved, $file): void {
            $settings = new Settings(['attachmentVolume' => $saved[1]->uid, 'attachmentVolumeId' => (int)$file[1]->id]);

            expect($settings->attachmentVolumeId)->toBe((int)$file[1]->id)
                ->and($settings->attachmentVolume)->toBe($file[1]->uid);

            // Later partial updates, such as the Spam settings or a pane's posted fields, keep the file's volume.
            $settings->setAttributes(['pluginName' => 'Comments'], false);

            expect($settings->attachmentVolumeId)->toBe((int)$file[1]->id)
                ->and($settings->attachmentVolume)->toBe($file[1]->uid);
        });

        attachmentUidWithConfigFile(['attachmentVolume' => $file[1]->uid], static function() use ($file): void {
            $settings = new Settings(['attachmentVolume' => $file[1]->uid]);

            expect($settings->attachmentVolumeId)->toBe((int)$file[1]->id);
        });

        // A file's 0 isn't a volume, and its null is ignored, as for other settings, so the saved volume stays.
        // `allowAttachments` is what turns attachments off on one environment.
        attachmentUidWithConfigFile(['attachmentVolumeId' => 0], static function() use ($saved): void {
            $settings = new Settings(['attachmentVolume' => $saved[1]->uid, 'attachmentVolumeId' => 0]);

            expect($settings->attachmentVolume)->toBe($saved[1]->uid)
                ->and($settings->attachmentVolumeId)->toBe((int)$saved[1]->id);
        });

        attachmentUidWithConfigFile(['attachmentVolumeId' => null], static function() use ($saved): void {
            $settings = new Settings(['attachmentVolume' => $saved[1]->uid, 'attachmentVolumeId' => null]);

            expect($settings->attachmentVolume)->toBe($saved[1]->uid)
                ->and($settings->attachmentVolumeId)->toBe((int)$saved[1]->id);
        });
    } finally {
        attachmentUidRemoveVolume($saved);
        attachmentUidRemoveVolume($file);
    }
});

it('leaves attachments without a volume for a UID that isn’t on this installation, without blocking saves', function() {
    $settings = new Settings(['allowAttachments' => true, 'attachmentVolume' => '00000000-0000-0000-0000-000000000000']);

    expect($settings->attachmentVolumeId)->toBeNull()
        ->and($settings->validate())->toBeTrue();

    // Choosing no volume with attachments on is still refused.
    $none = new Settings(['allowAttachments' => true, 'attachmentVolume' => '']);

    expect($none->validate())->toBeFalse()
        ->and($none->getErrors('attachmentVolumeId'))->not->toBeEmpty();
});

it('offers volumes by UID on the Authoring pane, with the saved one selected', function() {
    $created = attachmentUidVolume();
    [, $volume] = $created;
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode($view::TEMPLATE_MODE_CP);

    try {
        $html = $view->renderTemplate('comments/settings/_panes/authoring', [
            'settings' => new Settings(['authoringMode' => 'rich', 'allowAttachments' => true, 'attachmentVolume' => $volume->uid]),
        ]);

        expect($html)->toContain('name="attachmentVolume"')
            ->and($html)->not->toContain('name="attachmentVolumeId"')
            ->and($html)->toMatch('/<option value="' . preg_quote($volume->uid, '/') . '" selected>/');
    } finally {
        $view->setTemplateMode($mode);
        attachmentUidRemoveVolume($created);
    }
});
