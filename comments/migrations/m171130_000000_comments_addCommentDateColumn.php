<?php
namespace Craft;

/**
 * @author Michael Rog <michael@michaelrog.com>
 */
class m171130_000000_comments_addCommentDateColumn extends BaseMigration
{

	public function safeUp()
	{

		/*
		 * First create the new column
		 */
		$this->addColumnAfter('comments', 'commentDate', ColumnType::DateTime, 'comment');

		/*
		 * For existing records, backfill the new column with the existing values from dateCreated
		 */
		$table = craft()->db->addTablePrefix('comments');
		$this->execute("update {$table} set commentDate = dateCreated;");

		return true;

	}

}
