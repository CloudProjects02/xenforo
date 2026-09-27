<?php

namespace XF\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $prefix_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property-read string|\Stringable $title
 *
 * RELATIONS
 * @property-read Phrase|null $MasterTitle
 * @property-read AbstractCollection<ThreadPrefix> $Prefixes
 */
class ThreadPrefixGroup extends AbstractPrefixGroup
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrefix';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_thread_prefix_group',
			'XF:ThreadPrefixGroup',
			'XF:ThreadPrefix'
		);

		return $structure;
	}
}
