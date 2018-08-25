<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class VoteNotFoundException extends Exception
{
    public function getName()
    {
        return 'Vote not found';
    }
}
