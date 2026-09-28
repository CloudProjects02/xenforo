<?php

namespace DBTech\Shop\Entity;

use XF\Entity\AbstractPrefix;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $prefix_id
 * @property int $prefix_group_id
 * @property int $display_order
 * @property int $materialized_order
 * @property string $css_class
 * @property array $allowed_user_group_ids
 *
 * GETTERS
 * @property string|\XF\Phrase $title
 * @property bool $has_usage_help
 * @property array $category_ids
 *
 * RELATIONS
 * @property \XF\Entity\Phrase $MasterTitle
 * @property \DBTech\Shop\Entity\ItemPrefixGroup $PrefixGroup
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\CategoryPrefix[] $CategoryPrefixes
 */
class ItemPrefix extends AbstractPrefix
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
	 * @return array
	 */
	public function getCategoryIds(): array
	{
		if (!$this->prefix_id)
		{
			return [];
		}

		return $this->db()->fetchAllColumn('
			SELECT category_id
			FROM xf_dbtech_shop_category_prefix
			WHERE prefix_id = ?
		', $this->prefix_id);
	}

	/**
	 *
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		/** @var \DBTech\Shop\Repository\CategoryPrefix $categoryPrefixRepo */
		$categoryPrefixRepo = $this->repository('DBTech\Shop:CategoryPrefix');
		$categoryPrefixRepo->removePrefixAssociations($this);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		self::setupDefaultStructure($structure, 'xf_dbtech_shop_item_prefix', 'DBTech\Shop:ItemPrefix');

		$structure->getters['category_ids'] = true;

		$structure->relations['CategoryPrefixes'] = [
			'entity' => 'DBTech\Shop:CategoryPrefix',
			'type' => self::TO_MANY,
			'conditions' => 'prefix_id'
		];

		return $structure;
	}
}