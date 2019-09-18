<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class SubscribeNotFoundException extends Exception
{
    public function getName()
    {
        return 'Subscribe not found';
    }
}
