<?php

namespace DBTech\Shop\Entity;

use XF\Entity\AbstractPrefixGroup;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $prefix_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property \XF\Phrase|string $title
 *
 * RELATIONS
 * @property \XF\Entity\Phrase $MasterTitle
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\ItemPrefix[] $Prefixes
 */
class ItemPrefixGroup extends AbstractPrefixGroup
{
	/**
	 * @return string
	 */
	protected function getClassIdentifier(): string
	{
		return 'DBTech\Shop:ItemPrefix';
	}
	
	/**
	 * @return string
	 */
	protected static function getContentType(): string
	{
		return 'dbtechShopItem';
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		self::setupDefaultStructure(
			$structure,
			'xf_dbtech_shop_item_prefix_group',
			'DBTech\Shop:ItemPrefixGroup',
			'DBTech\Shop:ItemPrefix'
		);

		return $structure;
	}
}