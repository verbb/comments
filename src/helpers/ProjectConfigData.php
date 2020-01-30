<?php
namespace verbb\comments\helpers;

use verbb\comments\elements\Comment;

use Craft;
use craft\commerce\db\Table;

use craft\db\Query;
use craft\helpers\Json;

class ProjectConfigData
{
    public static function rebuildProjectConfig(): array
    {
        $output = [];

        $commentFieldLayout = Craft::$app->getFields()->getLayoutByType(Comment::class);

        if ($commentFieldLayout->uid) {
            $output['comments'] = [
                'fieldLayouts' => [
                    $commentFieldLayout->uid => $commentFieldLayout->getConfig()
                ]
            ];
        }

        return $output;
    }
}