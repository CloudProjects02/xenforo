<?php

namespace DBTech\Shop\Entity;

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
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\ItemPrefix> $Prefixes
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
	 * @param Structure $structure
	 *
	 * @return Structure
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