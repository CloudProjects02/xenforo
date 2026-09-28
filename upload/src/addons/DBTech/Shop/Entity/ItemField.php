<?php

namespace DBTech\Shop\Entity;

use XF\Entity\AbstractField;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $field_id
 * @property int $display_order
 * @property string $field_type
 * @property array $field_choices
 * @property string $match_type
 * @property array $match_params
 * @property int $max_length
 * @property bool $required
 * @property string $display_template
 * @property string $display_group
 *
 * GETTERS
 * @property \XF\Phrase $title
 * @property \XF\Phrase $description
 *
 * RELATIONS
 * @property \XF\Entity\Phrase $MasterTitle
 * @property \XF\Entity\Phrase $MasterDescription
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\CategoryField[] $CategoryFields
 */
class ItemField extends AbstractField
{
	/**
	 * @return string
	 */
	protected function getClassIdentifier(): string
	{
		return 'DBTech\Shop:ItemField';
	}

	/**
	 * @return string
	 */
	protected static function getPhrasePrefix(): string
	{
		return 'dbtech_shop_item_field';
	}

	/**
	 *
	 */
	protected function _postDelete()
	{
		/** @var \DBTech\Shop\Repository\CategoryField $repo */
		$repo = $this->repository('DBTech\Shop:CategoryField');
		$repo->removeFieldAssociations($this);
		
		$this->db()->delete('xf_dbtech_shop_item_field_value', 'field_id = ?', $this->field_id);
		
		parent::_postDelete();
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
			'xf_dbtech_shop_item_field',
			'DBTech\Shop:ItemField',
			[
				'groups' => ['above_main', 'above_info', 'below_info', 'new_tab']
			]
		);
		
		$structure->relations['CategoryFields'] = [
			'entity' => 'DBTech\Shop:CategoryField',
			'type' => self::TO_MANY,
			'conditions' => 'field_id'
		];

		return $structure;
	}
}