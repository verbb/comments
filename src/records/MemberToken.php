<?php
namespace verbb\comments\records;

use craft\db\ActiveRecord;

/**
 * A member token minted for a front end on another site. Only a hash of the token is stored, with the
 * member, the origin it's bound to, when it runs out and when it was revoked.
 */
class MemberToken extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%comments_member_tokens}}';
    }
}
