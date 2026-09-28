<?php

namespace XFES\Service;

use XF\App;
use XF\Search\MetadataStructure;
use XF\Service\AbstractService;
use XFES\Elasticsearch\Api;
use XFES\Elasticsearch\Exception;
use XFES\Elasticsearch\RequestException;

class Optimizer extends AbstractService
{
	protected $es;

	public function __construct(App $app, Api $es)
	{
		parent::__construct($app);

		$this->es = $es;
	}

	public function getEsApi()
	{
		return $this->es;
	}

	public function isOptimizable()
	{
		try
		{
			$config = $this->es->getIndexInfo();
		}
		catch (RequestException $e)
		{
			return true;
		}
		catch (Exception $e)
		{
			return false;
		}

		if (!$config || empty($config['mappings']))
		{
			return true;
		}

		$liveMappings = $config['mappings'];
		$expectedMappings = $this->getExpectedMappingConfig();

		return ($liveMappings != $expectedMappings);
	}

	public function optimize(array $settings = [], $updateConfig = false)
	{
		$config = [];

		if ($this->es->indexExists())
		{
			$config = $this->es->getIndexInfo();
			$this->es->deleteIndex();
			$this->db()->emptyTable('xf_es_index_failed');
		}

		if ($settings)
		{
			if (isset($config['settings']))
			{
				$config['settings'] = array_replace_recursive($config['settings'], $settings);
			}
			else
			{
				$config['settings'] = $settings;
			}
		}

		$config['mappings'] = $this->getExpectedMappingConfig();

		$this->es->createIndex($config);

		if ($updateConfig)
		{
			/** @var Configurer $configurer */
			$configurer = $this->service('XFES:Configurer', $this->es);
			$configurer->saveConfig();
		}

		return true;
	}

	public function getExpectedMappingConfig()
	{
		$mapping = $this->getBaseMapping();

		if (!empty($mapping['_source']['enabled']))
		{
			// this is the default and won't be returned
			unset($mapping['_source']);
		}

		foreach ($this->app->search()->getValidHandlers() AS $handler)
		{
			foreach ($handler->getMetadataStructure() AS $mdKey => $mdConfig)
			{
				switch ($mdConfig['type'])
				{
					case MetadataStructure::BOOL:
						$mdType = 'boolean';
						break;

					case MetadataStructure::FLOAT:
						$mdType = 'double';
						break;

					case MetadataStructure::INT:
						$mdType = 'long';
						break;

					case MetadataStructure::KEYWORD:
						$mdType = 'keyword';
						break;

					case MetadataStructure::STR:
					default:
						$mdType = 'text';
						break;
				}

				$mapping['properties'][$mdKey] = ['type' => $mdType];
			}
		}

		return $mapping;
	}

	protected function getBaseMapping()
	{
		$mapping = [
			'_source' => ['enabled' => false],
			'properties' => [
				'type' => ['type' => 'keyword'],
				'title' => ['type' => 'text'],
				'message' => ['type' => 'text'],
				'date' => ['type' => 'long', 'store' => true],
				'user' => ['type' => 'long', 'store' => true],
				'discussion_id' => ['type' => 'long', 'store' => true],
				'hidden' => ['type' => 'boolean'],
				'tag' => ['type' => 'long'], // this is actually an array of integers
			],
		];

		$suggestEnabled = $this->app->options()->searchSuggestions['enabled'];
		if ($suggestEnabled)
		{
			$mapping['properties']['title_suggest'] = [
				'type' => 'search_as_you_type',
				'doc_values' => false,
				'max_shingle_size' => 3,
				'analyzer' => 'suggest',
			];
		}

		return $mapping;
	}
}
