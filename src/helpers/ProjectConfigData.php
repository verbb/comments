<?php
namespace verbb\comments\helpers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\db\Query;
use craft\helpers\Json;
use craft\models\Structure;

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

        // Ensure the structure exists
        $settings = Comments::$plugin->getSettings();
        $structureUid = $settings->structureUid;

        if ($structureUid) {
            $structuresService = Craft::$app->getStructures();
            $structure = $structuresService->getStructureByUid($structureUid, true) ?? new Structure(['uid' => $structureUid]);
            
            $structuresService->saveStructure($structure);
        }

        return $output;
    }
}