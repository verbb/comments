<?php
namespace verbb\comments\models;

use verbb\comments\records\Flag as FlagRecord;

use Craft;
use craft\base\Model;
use craft\validators\UniqueValidator;

class Flag extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $commentId;
    public $userId;


    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['userId'], 'required', 'message' => Craft::t('comments', 'You must be logged in to flag a comment.')],
            [['commentId'], 'required'],
        ];
    }

}