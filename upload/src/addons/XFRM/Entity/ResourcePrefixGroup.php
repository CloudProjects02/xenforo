<?php

namespace XFRM\Entity;

use XF\Entity\AbstractPrefixGroup;
use XF\Entity\Phrase;
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
 * @property-read AbstractCollection<ResourcePrefix> $Prefixes
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
