<?php

namespace XFRM\Entity;

use XF\Entity\AbstractPrefixGroup;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $prefix_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property-read Phrase|string $title
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XFRM\Entity\ResourcePrefix> $Prefixes
 */
class ResourcePrefixGroup extends AbstractPrefixGroup
{
	protected function getClassIdentifier()
	{
		return 'XFRM:ResourcePrefix';
	}

	protected static function getContentType()
	{
		return 'resource';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_rm_resource_prefix_group',
			'XFRM:ResourcePrefixGroup',
			'XFRM:ResourcePrefix'
		);

		return $structure;
	}
}
