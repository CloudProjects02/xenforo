<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Repository\CategoryPrefixRepository;
use XF\Entity\AbstractPrefix;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

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
 * @property-read string|Phrase $title
 * @property-read bool $has_usage_help
 * @property-read array $category_ids
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read ItemPrefixGroup|null $PrefixGroup
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\CategoryPrefix> $CategoryPrefixes
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
	protected function _postDelete(): void
	{
		parent::_postDelete();

		$categoryPrefixRepo = \XF::app()->repository(CategoryPrefixRepository::class);
		$categoryPrefixRepo->removePrefixAssociations($this);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		self::setupDefaultStructure($structure, 'xf_dbtech_shop_item_prefix', 'DBTech\Shop:ItemPrefix');

		$structure->getters['category_ids'] = true;

		$structure->relations['CategoryPrefixes'] = [
			'entity' => CategoryPrefix::class,
			'type' => self::TO_MANY,
			'conditions' => 'prefix_id',
		];

		return $structure;
	}
}