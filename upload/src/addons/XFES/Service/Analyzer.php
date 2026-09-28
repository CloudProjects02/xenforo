<?php

namespace XFES\Service;

use XF\App;
use XF\Service\AbstractService;
use XF\Util\Arr;
use XFES\Elasticsearch\Api;

use function is_array;

class Analyzer extends AbstractService
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

	public function updateAnalyzer(array $config)
	{
		$settings = $this->getAnalyzerFromConfig($config);

		if ($this->es->indexExists())
		{
			$this->es->closeIndex();

			try
			{
				$this->es->updateSettings($settings);
			}
			finally
			{
				$this->es->openIndex();
			}
		}
		else
		{
			/** @var Optimizer $optimizer */
			$optimizer = $this->service('XFES:Optimizer', $this->es);
			$optimizer->optimize($settings, true);
		}
	}

	public function getDefaultConfig()
	{
		return [
			'unknown' => false,
			'stop' => 'none',
			'stop_language' => '_english_',
			'stop_custom' => '',
			'stemming' => 'none',
			'stemming_language' => 'porter2',
			'ascii_folding' => false,
		];
	}

	public function getAnalyzerFromConfig(array $config)
	{
		$config = array_replace($this->getDefaultConfig(), $config);

		$filters = [];
		$customFilters = [];

		if ($config['ascii_folding'])
		{
			$filters[] = 'asciifolding';
		}

		$filters[] = 'lowercase';

		if ($config['stop'] == 'language' && preg_match('/^_[a-z]+_$/', $config['stop_language']))
		{
			$customFilters['xf_stop'] = [
				'type' => 'stop',
				'stopwords' => $config['stop_language'],
			];

			$filters[] = 'xf_stop';
		}
		else if ($config['stop'] == 'custom')
		{
			$customStop = Arr::stringToArray($config['stop_custom']);

			if ($customStop)
			{
				$customFilters['xf_stop'] = [
					'type' => 'stop',
					'stopwords' => $customStop,
				];

				$filters[] = 'xf_stop';
			}
		}

		$suggestFilters = $filters;

		if ($config['stemming'] == 'language' && $config['stemming_language'])
		{
			$customFilters['xf_stemmer'] = [
				'type' => 'stemmer',
				'language' => $config['stemming_language'],
			];

			$filters[] = 'xf_stemmer';
		}

		$result = [
			'analysis' => [
				'filter' => $customFilters,
				'analyzer' => [
					'default' => [
						'type' => 'custom',
						'tokenizer' => 'standard',
						'filter' => $filters,
					],
					'suggest' => [
						'type' => 'custom',
						'tokenizer' => 'standard',
						'filter' => $suggestFilters,
					],
				],
			],
		];

		if (!$customFilters)
		{
			// If we just pass an empty array, that can change how ES returns it's results.
			// Instead of [filter][name] we get [filter.name] in the response.
			unset($result['analysis']['filter']);
		}

		return $result;
	}

	public function getCurrentConfig()
	{
		if ($this->es->indexExists())
		{
			$info = $this->es->getIndexInfo();
			if (!empty($info['settings']['index']['analysis']))
			{
				return $this->getConfigFromAnalyzer($info['settings']['index']['analysis']);
			}
		}

		return $this->getDefaultConfig();
	}

	public function getConfigFromAnalyzer(array $analysis)
	{
		$config = $this->getDefaultConfig();

		if (!isset($analysis['analyzer']['default']))
		{
			$config['unknown'] = true;
			return $config;
		}

		$default = array_replace([
			'type' => null,
			'tokenizer' => null,
			'filter' => [],
			'char_filter' => [],
		], $analysis['analyzer']['default']);

		$unknown = false;

		if ($default['type'] != 'custom' || $default['tokenizer'] != 'standard')
		{
			$unknown = true;
		}

		if (!empty($default['char_filter']))
		{
			$unknown = true;
		}

		// There are 2 ways ES seems to return filter definitions: [filter][filter_name] and [filter.filter_name]

		if (isset($analysis['filter']['xf_stop']))
		{
			$stopWordFilter = $analysis['filter']['xf_stop'];
		}
		else if (isset($analysis['filter.xf_stop']))
		{
			$stopWordFilter = $analysis['filter.xf_stop'];
		}
		else
		{
			$stopWordFilter = null;
		}
		if ($stopWordFilter && isset($stopWordFilter['stopwords']))
		{
			$stopwords = $stopWordFilter['stopwords'];
			if (is_array($stopwords))
			{
				$config['stop'] = 'custom';
				$config['stop_custom'] = implode(' ', $stopwords);
			}
			else
			{
				$config['stop'] = 'language';
				$config['stop_language'] = $stopwords;
			}
		}

		if (isset($analysis['filter']['xf_stemmer']))
		{
			$stemmerFilter = $analysis['filter']['xf_stemmer'];
		}
		else if (isset($analysis['filter.xf_stemmer']))
		{
			$stemmerFilter = $analysis['filter.xf_stemmer'];
		}
		else
		{
			$stemmerFilter = null;
		}
		if ($stemmerFilter && isset($stemmerFilter['language']))
		{
			$config['stemming'] = 'language';
			$config['stemming_language'] = $stemmerFilter['language'];
		}

		$hasStop = false;
		$hasStemming = false;

		foreach ($default['filter'] AS $filter)
		{
			switch ($filter)
			{
				case 'lowercase':
					break;

				case 'xf_stop':
					$hasStop = true;
					break;

				case 'xf_stemmer':
					$hasStemming = true;
					break;

				case 'asciifolding':
					$config['ascii_folding'] = true;
					break;

				default:
					$unknown = true;
			}
		}

		if (!$hasStop)
		{
			$config['stop'] = 'none';
		}
		if (!$hasStemming)
		{
			$config['stemming'] = 'none';
		}

		$config['unknown'] = $unknown;

		return $config;
	}
}
