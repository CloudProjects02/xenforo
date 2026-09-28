<?php

namespace XFES;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XFES\Elasticsearch\Api;
use XFES\Install\Data\MySql;

class Setup extends AbstractSetup
{
	use StepRunnerUpgradeTrait;

	public function install(array $stepParams = [])
	{
		$sm = $this->schemaManager();
		foreach ($this->getTables() AS $table => $schema)
		{
			$sm->createTable($table, $schema);
		}

		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$widgetFn($widgetKey);
		}
	}

	public function upgrade1010010Step1()
	{
		$this->schemaManager()->createTable('xf_es_search_failed', function (Create $table)
		{
			$table->addColumn('search_failed_id', 'int')->autoIncrement();
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('content_id', 'int');
			$table->addColumn('action', 'varchar', 25);
			$table->addColumn('data', 'mediumblob');
			$table->addColumn('fail_count', 'smallint')->setDefault(0);
			$table->addColumn('reindex_date', 'int')->setDefault(0);
			$table->addUniqueKey(['content_type', 'content_id'], 'content_type');
			$table->addKey('reindex_date');
		});
	}

	public function upgrade2000010Step1()
	{
		// convert the old options to the new structure
		$options = $this->app->options();

		$this->query("
			REPLACE INTO xf_option
				(option_id, option_value, default_value,
				edit_format, edit_format_params, data_type, sub_options,
				validation_class, validation_method, advanced, addon_id)
			VALUES
				('xfesEnabled', ?, '',
				'onoff', '', 'boolean', '',
				 '', '', 1, 'XFES')
		", $options->enableElasticsearch ? 1 : 0);

		$newConfigValue = [
			'host' => $options->elasticSearchServer['host'],
			'port' => $options->elasticSearchServer['port'],
			'https' => false,
			'username' => '',
			'password' => '',
			'index' => $options->elasticSearchIndex,
			'singleType' => $options->elasticSearchSingleType ?? false,
		];

		$this->query("
			REPLACE INTO xf_option
				(option_id, option_value, default_value,
				 edit_format, edit_format_params, data_type, sub_options,
				validation_class, validation_method, advanced, addon_id)
			VALUES
				('xfesConfig', ?, '',
				'template', '', 'array', '*',
				 '', '', 1, 'XFES')
		", [json_encode($newConfigValue)]);

		if (isset($options->esRecencyWeightedRelevance))
		{
			$newRecencyValue = [
				'enabled' => $options->esRecencyWeightedRelevance['enabled'] ? true : false,
				'halfLife' => $options->esRecencyWeightedRelevance['halfLifeDays'],
			];

			$this->query("
				REPLACE INTO xf_option
					(option_id, option_value, default_value,
					 edit_format, edit_format_params, data_type, sub_options,
					validation_class, validation_method, advanced, addon_id)
				VALUES
					('xfesRecencyRelevance', ?, '',
					'template', '', 'array', '*',
					 '', '', 1, 'XFES')
			", [json_encode($newRecencyValue)]);
		}

		$this->db()->emptyTable('xf_es_search_failed');

		$this->schemaManager()->alterTable('xf_es_search_failed', function (Alter $alter)
		{
			$alter->renameTo('xf_es_index_failed');
			$alter->renameColumn('search_failed_id', 'index_failed_id');
			$alter->changeColumn('reindex_date')->unsigned(true);
		});
	}

	public function upgrade2020010Step1()
	{
		$this->schemaManager()->createTable(
			'xf_es_thread_similar',
			function (Create $table)
			{
				$table->addColumn('thread_id', 'int');
				$table->addColumn('last_update_date', 'int')->setDefault(0);
				$table->addColumn('pending_rebuild', 'tinyint')->setDefault(0);
				$table->addColumn('similar_thread_ids', 'blob');
				$table->addPrimaryKey('thread_id');
				$table->addKey('pending_rebuild');
			}
		);

		$this->insertNamedWidget('xfes_thread_view_below_quick_reply_similar_threads');
	}

	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		if ($previousVersion < 2000010)
		{
			$app = \XF::app();
			$options = $app->options();

			if (!empty($options->xfesEnabled) && !empty($options->xfesConfig))
			{
				$es = new Api($options->xfesConfig);
				try
				{
					if (!$es->test())
					{
						// doesn't meet requirements, so we need to disable
						$app->repository('XF:Option')->updateOption('xfesEnabled', false);
					}
				}
				catch (\Exception $e)
				{
				}
			}

			$stateChanges['redirect'] = $app->router('admin')->buildLink('enhanced-search');
		}

		// the following runs after every upgrade
		$this->enqueuePostUpgradeCleanUp();
	}

	public function uninstall(array $stepParams = [])
	{
		$sm = $this->schemaManager();
		foreach (array_keys($this->getTables()) AS $table)
		{
			$sm->dropTable($table);
		}
	}

	/**
	 * @return \Closure[]
	 */
	public function getTables()
	{
		$data = new MySql();
		return $data->getTables();
	}

	/**
	 * @return \Closure[]
	 */
	protected function getDefaultWidgetSetup()
	{
		return [
			'xfes_thread_view_below_quick_reply_similar_threads' => function (
				$key,
				array $options = []
			)
			{
				$options = array_replace([
					'style' => 'full',
				], $options);

				$this->createWidget(
					$key,
					'xfes_similar_threads',
					[
						'positions' => ['thread_view_below_quick_reply' => 100],
						'options' => $options,
					]
				);
			},
		];
	}

	/**
	 * @param string $key
	 * @param array  $options
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function insertNamedWidget($key, array $options = [])
	{
		$widgets = $this->getDefaultWidgetSetup();
		if (!isset($widgets[$key]))
		{
			throw new \InvalidArgumentException("Unknown widget '$key'");
		}

		$widgetFn = $widgets[$key];
		$widgetFn($key, $options);
	}
}
