<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class FlagNotFoundException extends Exception
{
    public function getName()
    {
        return 'Flag not found';
    }
}
