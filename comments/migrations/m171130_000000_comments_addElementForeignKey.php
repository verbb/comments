<?php
namespace Craft;

/**
 * @author Michael Rog <michael@michaelrog.com>
 */
class m171130_000000_comments_addElementForeignKey extends BaseMigration
{

	public function safeUp()
	{

		/*
		 * The `id` of each Comment record should be tied to the corresponding Element record,
		 * so that when Elements are deleted by Craft - i.e. in `CommentsService:deleteComment()` -
		 * the associated Comments records will be deleted by cascade. Otherwise orphans will be left in the `comments` table.
		 */

		/*
		 * Adding a foreign key will fail if we already have orphan records in the `comments` table.
		 * So, first, we must delete all the Comment records that don't have an associated Element record anymore.
		 */

		// Get the IDs of any orphaned comments...
		$ids = craft()->db->createCommand()
			->select('c.id')
			->from('comments c')
			->leftJoin('elements e', 'c.id = e.id')
			->where('e.id is null')
			->queryColumn();

		// ...and nuke 'em.
		if ($ids)
		{
			$this->delete('comments', array('in', 'id', $ids));
		}

		/*
		 * Now we can safely add the FK.
		 */

		craft()->db->createCommand()->addForeignKey('comments', 'id', 'elements', 'id', 'CASCADE', null);

		return true;

	}

}
