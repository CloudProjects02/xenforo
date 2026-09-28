<?php

namespace XFES\Install\Data;

use XF\Db\Schema\Create;
use XF\Install\Data\AbstractMySql;

class MySql extends AbstractMySql
{
	public function getTables(): array
	{
		$tables = [];

		$tables['xf_es_index_failed'] = function (Create $table)
		{
			$table->addColumn('index_failed_id', 'int')->autoIncrement();
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('content_id', 'int');
			$table->addColumn('action', 'varchar', 25);
			$table->addColumn('data', 'mediumblob');
			$table->addColumn('fail_count', 'smallint')->setDefault(0);
			$table->addColumn('reindex_date', 'int')->setDefault(0);
			$table->addUniqueKey(['content_type', 'content_id'], 'content_type');
			$table->addKey('reindex_date');
		};

		$tables['xf_es_thread_similar'] = function (Create $table)
		{
			$table->addColumn('thread_id', 'int');
			$table->addColumn('last_update_date', 'int')->setDefault(0);
			$table->addColumn('pending_rebuild', 'tinyint')->setDefault(0);
			$table->addColumn('similar_thread_ids', 'blob');
			$table->addPrimaryKey('thread_id');
			$table->addKey('pending_rebuild');
		};

		return $tables;
	}

	public function getData(): array
	{
		return [];
	}
}
