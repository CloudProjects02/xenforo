<?php

namespace XFES\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $index_failed_id
 * @property string $content_type
 * @property int $content_id
 * @property array|null $data
 * @property string $action
 * @property int $fail_count
 * @property int $reindex_date
 */
class IndexFailed extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_es_index_failed';
		$structure->shortName = 'XFES:IndexFailed';
		$structure->primaryKey = 'index_failed_id';
		$structure->columns = [
			'index_failed_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id' => ['type' => self::UINT, 'required' => true],
			'data' => ['type' => self::JSON_ARRAY, 'required' => true],
			'action' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'fail_count' => ['type' => self::UINT, 'default' => 0],
			'reindex_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}
