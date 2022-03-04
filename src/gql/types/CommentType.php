<?php
namespace verbb\comments\gql\types;

use verbb\comments\gql\interfaces\CommentInterface;

use craft\gql\types\elements\Element;

class CommentType extends Element
{
    // Public Methods
    // =========================================================================

    public function __construct(array $config)
    {
        $config['interfaces'] = [
            CommentInterface::getType(),
        ];

        parent::__construct($config);
    }
}
