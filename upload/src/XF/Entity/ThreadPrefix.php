<?php

namespace XF\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Structure;
use XF\Repository\ForumPrefixRepository;

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
 * @property-read string|\Stringable $title
 * @property-read bool $has_usage_help
 * @property-read string|\Stringable $description
 * @property-read string|\Stringable $usage_help
 *
 * RELATIONS
 * @property-read Phrase|null $MasterTitle
 * @property-read ThreadPrefixGroup|null $PrefixGroup
 * @property-read Phrase|null $MasterDescription
 * @property-read Phrase|null $MasterUsageHelp
 * @property-read AbstractCollection<ForumPrefix> $ForumPrefixes
 */
class ThreadPrefix extends AbstractPrefix
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrefix';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$this->repository(ForumPrefixRepository::class)->removePrefixAssociations($this);
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_thread_prefix', 'XF:ThreadPrefix', [
			'has_description' => true,
			'has_usage_help' => true,
		]);

		$structure->relations['ForumPrefixes'] = [
			'entity' => 'XF:ForumPrefix',
			'type' => self::TO_MANY,
			'conditions' => 'prefix_id',
		];

		return $structure;
	}
}
