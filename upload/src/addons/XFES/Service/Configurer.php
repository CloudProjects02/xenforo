<?php

namespace XFES\Service;

use XF\App;
use XF\Service\AbstractService;
use XFES\Elasticsearch\Api;
use XFES\Listener;

use function is_array;

class Configurer extends AbstractService
{
	protected $es;

	public function __construct(App $app, $config = null)
	{
		parent::__construct($app);

		if ($config instanceof Api)
		{
			$this->es = $config;
		}
		else if (is_array($config) || $config === null)
		{
			$this->es = Listener::getElasticsearchApi($config);
		}
		else
		{
			throw new \LogicException("Config must be \XFES\Elasticsearch\Api, array or null");
		}
	}

	public function hasActiveConfig()
	{
		return $this->es['host'] ? true : false;
	}

	public function isEnabled()
	{
		return $this->app->options()->xfesEnabled;
	}

	public function getEsApi()
	{
		return $this->es;
	}

	public function test(&$error = null)
	{
		return $this->es->test($error);
	}

	public function indexExists()
	{
		return $this->es->indexExists();
	}

	public function getAnalyzerConfig()
	{
		/** @var Analyzer $analyzer */
		$analyzer = $this->service('XFES:Analyzer', $this->es);
		return $analyzer->getCurrentConfig();
	}

	public function initializeIndex(array $analyzerConfig)
	{
		/** @var Analyzer $analyzer */
		$analyzer = $this->service('XFES:Analyzer', $this->es);
		$analyzerDsl = $analyzer->getAnalyzerFromConfig($analyzerConfig);

		/** @var Optimizer $optimizer */
		$optimizer = $this->service('XFES:Optimizer', $this->es);
		$optimizer->optimize($analyzerDsl);
	}

	public function enable($emptyMySql = false)
	{
		$this->saveEnabledOption(true);

		if ($emptyMySql)
		{
			$this->db()->emptyTable('xf_search_index');
		}
	}

	public function disable()
	{
		$this->saveEnabledOption(false);
	}

	public function saveConfig()
	{
		$config = $this->es->getConfig();
		$this->saveConfigOption($config);

		return $config;
	}

	public function mergeOptions(array $options)
	{
		$config = $this->es->getConfig();
		$config = array_replace($config, $options);

		$this->saveConfigOption($config);

		return $config;
	}

	protected function saveConfigOption(array $value)
	{
		$this->repository('XF:Option')->updateOption('xfesConfig', $value);
	}

	protected function saveEnabledOption($value)
	{
		$this->repository('XF:Option')->updateOption('xfesEnabled', $value);
	}
}
