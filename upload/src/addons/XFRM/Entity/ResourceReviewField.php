<?php

namespace XFRM\Entity;

use XF\Entity\AbstractField;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XFRM\Repository\CategoryField;

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
 * @property string $wrapper_template
 *
 * GETTERS
 * @property-read Phrase $title
 * @property-read Phrase $description
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XFRM\Entity\CategoryReviewField> $CategoryReviewFields
 */
class ResourceReviewField extends AbstractField
{
	protected function getClassIdentifier()
	{
		return 'XFRM:ResourceReviewField';
	}

	protected static function getPhrasePrefix()
	{
		return 'xfrm_resource_review_field';
	}

	protected function _postDelete()
	{
		/** @var CategoryField $repo */
		$repo = $this->repository('XFRM:CategoryReviewField');
		$repo->removeFieldAssociations($this);

		$this->db()->delete('xf_rm_resource_review_field_value', 'field_id = ?', $this->field_id);

		parent::_postDelete();
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_rm_resource_review_field',
			'XFRM:ResourceReviewField',
			[
				'groups' => ['above_review', 'below_review'],
				'has_wrapper_template' => true,
			]
		);

		$structure->relations['CategoryReviewFields'] = [
			'entity' => 'XFRM:CategoryReviewField',
			'type' => self::TO_MANY,
			'conditions' => 'field_id',
		];

		return $structure;
	}
}
