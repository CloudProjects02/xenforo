<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Repository\CategoryFieldRepository;
use XF\Entity\AbstractField;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

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
 * @property-read Phrase $title
 * @property-read Phrase $description
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\CategoryField> $CategoryFields
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
	protected function _postDelete(): void
	{
		$repo = \XF::app()->repository(CategoryFieldRepository::class);
		$repo->removeFieldAssociations($this);

		$this->db()->delete('xf_dbtech_shop_item_field_value', 'field_id = ?', $this->field_id);

		parent::_postDelete();
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
			'xf_dbtech_shop_item_field',
			'DBTech\Shop:ItemField',
			[
				'groups' => ['above_main', 'above_info', 'below_info', 'new_tab'],
			]
		);

		$structure->relations['CategoryFields'] = [
			'entity' => CategoryField::class,
			'type' => self::TO_MANY,
			'conditions' => 'field_id',
		];

		return $structure;
	}
}