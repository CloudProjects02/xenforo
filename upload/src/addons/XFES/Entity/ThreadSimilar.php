<?php

namespace XFES\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $thread_id
 * @property int $last_update_date
 * @property bool $pending_rebuild
 * @property array $similar_thread_ids
 */
class ThreadSimilar extends Entity
{
	/**
	 * @var int
	 */
	public const MAX_RESULTS = 100;

	public function isRebuildRequired()
	{
		return ($this->last_update_date < \XF::$time - 14 * 86400);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_es_thread_similar';
		$structure->shortName = 'XFES:ThreadSimilar';
		$structure->primaryKey = 'thread_id';
		$structure->columns = [
			'thread_id' => [
				'type' => self::UINT,
				'required' => true,
			],
			'last_update_date' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'pending_rebuild' => [
				'type' => self::BOOL,
				'default' => false,
			],
			'similar_thread_ids' => [
				'type' => self::LIST_COMMA,
				'default' => [],
				'list' => [
					'type' => 'posint',
					'unique' => true,
				],
			],
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}
