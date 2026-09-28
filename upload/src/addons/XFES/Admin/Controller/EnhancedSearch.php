<?php

namespace XFES\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Entity\Forum;
use XF\Mvc\ParameterBag;
use XF\Repository\Node;
use XFES\Elasticsearch\Api;
use XFES\Listener;
use XFES\Search\Source\Elasticsearch;
use XFES\Service\Analyzer;
use XFES\Service\Configurer;
use XFES\Service\Optimizer;
use XFES\XF\Repository\Thread;

use function is_array;

class EnhancedSearch extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('option');
	}

	public function actionIndex()
	{
		$configurer = $this->getConfigurer();

		$es = null;
		$version = null;
		$distribution = null;
		$distributionVersion = null;
		$testError = null;
		$stats = null;
		$isOptimizable = false;
		$analyzerConfig = null;

		if ($configurer->hasActiveConfig())
		{
			$es = $configurer->getEsApi();

			try
			{
				$version = $es->version();
				$distribution = $es->distribution();
				$distributionVersion = $es->distributionVersion();

				if ($version && $es->test($testError))
				{
					if ($es->indexExists())
					{
						$isOptimizable = $this->getOptimizer($es)->isOptimizable();
						$stats = $this->service('XFES:Stats', $es)->getStats();
						$analyzerConfig = $this->getAnalyzer($es)->getCurrentConfig();
					}
					else
					{
						$isOptimizable = true;
					}
				}
			}
			catch (\XFES\Elasticsearch\Exception $e)
			{
			}
		}

		/** @var Node $nodeRepo */
		$nodeRepo = $this->repository('XF:Node');
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
		// only list nodes that are forums or contain forums
		$nodeTree = $nodeTree->filter(
			null,
			function ($id, $node, $depth, $children, $tree)
			{
				return ($children || $node->node_type_id == 'Forum');
			}
		);

		$viewParams = [
			'es' => $es,
			'version' => $version,
			'distribution' => $distribution,
			'distributionVersion' => $distributionVersion,
			'testError' => $testError,
			'stats' => $stats,
			'isOptimizable' => $isOptimizable,
			'analyzerConfig' => $analyzerConfig,
			'nodeTree' => $nodeTree,
			'reindex' => $this->filter('reindex', 'bool'),
		];
		return $this->view('XFES:EnhancedSearch\Index', 'xfes_index', $viewParams);
	}

	public function actionConfig()
	{
		if ($this->isPost())
		{
			$config = $this->filter([
				'host' => 'str',
				'port' => 'uint',
				'username' => 'str',
				'password' => 'str',
				'https' => 'bool',
				'index' => 'str',
			]);

			if ($config['https'])
			{
				$httpsVerify = $this->filter('https_verify', 'str');
				if ($httpsVerify === 'custom')
				{
					$config['verify'] = $this->filter('https_verify_bundle', 'str');
				}
				else if ($httpsVerify === 'disabled')
				{
					$config['verify'] = false;
				}
			}

			$configurer = $this->getConfigurer($config);

			if (!$configurer->test($error))
			{
				return $this->error($error);
			}

			if ($configurer->indexExists())
			{
				// don't manipulate any of this now
				$indexExists = true;
				$analyzerConfig = null;
			}
			else
			{
				$indexExists = false;
				$analyzerConfig = $configurer->getAnalyzerConfig();
			}

			$viewParams = [
				'config' => $config,
				'es' => $configurer->getEsApi(),
				'indexExists' => $indexExists,
				'analyzerConfig' => $analyzerConfig,
			];
			return $this->view('XFES:EnhancedSearch\ConfigConfirm', 'xfes_config_confirm', $viewParams);
		}
		else
		{
			return $this->view('XFES:EnhancedSearch\Config', 'xfes_config');
		}
	}

	public function actionSetup()
	{
		$this->assertPostOnly();

		$input = $this->filter([
			'config' => 'json-array',
			'enable' => 'bool',
			'empty_mysql' => 'bool',
			'index' => 'array',
		]);

		$configurer = $this->getConfigurer($input['config']);

		if (!$configurer->test($error))
		{
			return $this->error($error);
		}

		if (!$configurer->indexExists())
		{
			$configurer->initializeIndex($input['index']);
		}

		$configurer->saveConfig();

		if ($input['enable'])
		{
			$configurer->enable($input['empty_mysql']);
		}
		else
		{
			$configurer->disable();
		}

		return $this->redirect($this->buildLink('enhanced-search', null, ['reindex' => 1]));
	}

	public function actionOptimize()
	{
		if ($this->isPost())
		{
			$optimizer = $this->getOptimizer();

			$analyzer = $this->getAnalyzer($optimizer->getEsApi());
			$settings = $analyzer->getAnalyzerFromConfig($analyzer->getCurrentConfig());

			$optimizer->optimize($settings, true);

			return $this->redirect($this->buildLink('enhanced-search', null, ['reindex' => 1]));
		}
		else
		{
			$viewParams = [];
			return $this->view('XFES:EnhancedSearch\Optimize', 'xfes_optimize', $viewParams);
		}
	}

	public function actionToggle()
	{
		$configurer = $this->getConfigurer();

		if ($this->isPost())
		{
			$reindex = false;

			if ($configurer->isEnabled())
			{
				$configurer->disable();
			}
			else
			{
				$emptyMySql = $this->filter('empty_mysql', 'bool');
				$configurer->enable($emptyMySql);

				$reindex = true;
			}

			return $this->redirect($this->buildLink('enhanced-search', null, ['reindex' => $reindex]));
		}
		else
		{
			if (!$configurer->isEnabled() && !$configurer->getEsApi()->test($error))
			{
				return $this->error($error);
			}

			$viewParams = [
				'enabled' => $configurer->isEnabled(),
			];
			return $this->view('XFES:EnhancedSearch\Toggle', 'xfes_toggle', $viewParams);
		}
	}

	public function actionIndexConfig()
	{
		$this->assertPostOnly();

		$config = $this->filter('index', 'array');

		$changer = $this->getAnalyzer();
		$changer->updateAnalyzer($config);

		return $this->redirect($this->buildLink('enhanced-search', null, ['reindex' => 1]));
	}

	public function actionOptions()
	{
		$this->assertPostOnly();

		$options = $this->getDynamicOptionValuesFromInput();

		$this->repository('XF:Option')->updateOptions($options);

		$clearSimilarThreads = $this->filter('clear_similar_threads', 'bool');
		if ($clearSimilarThreads)
		{
			/** @var Thread $threadRepo */
			$threadRepo = $this->repository('XF:Thread');
			$threadRepo->clearSimilarThreadsCaches();
		}

		return $this->redirect($this->buildLink('enhanced-search'));
	}

	protected function getDynamicOptionValuesFromInput()
	{
		return [
			'xfesRecencyRelevance' => [
				'enabled' => $this->filter('recencyWeighted', 'bool'),
				'halfLife' => $this->filter('recencyHalfLife', 'uint'),
			],
			'xfesSimilarThreads' => [
				'suggestionsEnabled' => $this->filter('similarThreadsSuggestions', 'bool'),
				'widgetEnabled' => $this->filter('similarThreadsWidget', 'bool'),
				'maxResults' => $this->filter('similarThreadsMax', 'posint'),
				'forumBoost' => max($this->filter('similarThreadsForumBoost', 'num'), 1),
			],
			'xfesSimilarThreadsExcludedForums' => $this->filter('similarThreadsExcludedForums', 'array-uint'),
		];
	}

	public function actionMltAnalyzer()
	{
		$this->assertDebugMode();

		$viewParams = [];

		/** @var Node $nodeRepo */
		$nodeRepo = \XF::repository('XF:Node');
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

		// only list nodes that are forums or contain forums
		$nodeTree = $nodeTree->filter(null, function ($id, $node, $depth, $children, $tree)
		{
			return ($children || $node->node_type_id == 'Forum');
		});
		$viewParams['nodeTree'] = $nodeTree;

		$inputThread = null;
		$rawQuery = null;

		$type = $this->filter('type', 'str');
		if ($type == 'thread_creation')
		{
			$title = $this->filter('title', 'str');
			$nodeId = $this->filter('node_id', 'uint');

			$viewParams['creationTitle'] = $title;
			$viewParams['creationNodeId'] = $nodeId;

			/** @var Forum $forum */
			$forum = $this->assertRecordExists('XF:Forum', $nodeId);

			$inputThread = $forum->getNewThread();
			$inputThread->title = $title;
		}
		else if ($type == 'similar_threads')
		{
			$threadUrl = $this->filter('thread_url', 'str');
			$viewParams['threadUrl'] = $threadUrl;

			$inputThread = $this->repository('XF:Thread')->getThreadFromUrl($threadUrl, 'public', $error);
			if (!$inputThread)
			{
				return $this->error($error);
			}
		}
		else if ($type == 'raw_query')
		{
			$rawQuery = $this->filter('query', 'str');

			$viewParams['query'] = $rawQuery;
		}
		else
		{
			$type = 'thread_creation';
		}

		$searchSource = $this->app['search.source'];
		if (!($searchSource instanceof Elasticsearch))
		{
			return $this->error("Search source is not Elasticsearch. Cannot continue.");
		}

		$results = null;

		if ($inputThread)
		{
			/** @var Thread $threadRepo */
			$threadRepo = $this->repository('XF:Thread');
			$query = $threadRepo->getSimilarThreadsMltQuery($inputThread);

			$dsl = $searchSource->getMLTSearchDsl($query, 20);

			$es = $searchSource->getEsApi();
			$results = $es->search($dsl);

		}
		else if ($rawQuery)
		{
			$dsl = json_decode($rawQuery, true);
			if (!is_array($dsl))
			{
				return $this->error("Invalid query JSON");
			}

			$es = $searchSource->getEsApi();
			try
			{
				$results = $es->search($dsl);
			}
			catch (\Exception $e)
			{
				return $this->error("Query exception: " . $e->getMessage());
			}
		}

		if ($results !== null)
		{
			if (!isset($results['hits']['hits']))
			{
				return $this->error("MLT query did not return any hits.");
			}

			$threadScores = [];
			foreach ($results['hits']['hits'] AS $hit)
			{
				$typeAndId = $es->getTypeAndIdFromHit($hit);
				$threadScores[$typeAndId[1]] = $hit['_score'];
			}

			$threads = $this->em()->findByIds('XF:Thread', array_keys($threadScores));

			$viewParams['dsl'] = $dsl ?? null;
			$viewParams['threadScores'] = $threadScores;
			$viewParams['threads'] = $threads;
		}

		$viewParams['type'] = $type;
		$viewParams['inputThread'] = $inputThread;

		return $this->view('XFES:EnhancedSearch\Toggle', 'xfes_mlt_analyzer', $viewParams);
	}

	/**
	 * @param array|null $config
	 *
	 * @return Configurer
	 */
	protected function getConfigurer(?array $config = null)
	{
		return $this->service('XFES:Configurer', $config);
	}

	/**
	 * @param Api|null $es
	 *
	 * @return Optimizer
	 */
	protected function getOptimizer(?Api $es = null)
	{
		return $this->service('XFES:Optimizer', $es ?: Listener::getElasticsearchApi());
	}

	/**
	 * @param Api|null $es
	 *
	 * @return Analyzer
	 */
	protected function getAnalyzer(?Api $es = null)
	{
		return $this->service('XFES:Analyzer', $es ?: Listener::getElasticsearchApi());
	}
}
