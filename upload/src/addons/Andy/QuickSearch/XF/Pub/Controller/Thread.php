<?php

namespace Andy\QuickSearch\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
	public function actionQuickSearch(ParameterBag $params)
	{
		//########################################
		// show quick search in thread_view
		//########################################

		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> Quick search -> Maximum results
		$maximumResults = $options->quickSearchMaximumResults;
		
		// get options from Admin CP -> Options -> Quick search -> Exclude forums
		$excludeForums = $options->quickSearchExcludeForums;
		
		// get options from Admin CP -> Options -> Quick search -> Enhanced search
		$enhancedSearch = $options->quickSearchEnhancedSearch;
		
		// get thread
		$thread = $this->assertViewableThread($params->thread_id);

		// get currentThreadId
		$currentThreadId = $thread['thread_id'];
        
		// get nodeId
		$nodeId = $thread->node_id;
		
		// check condition
		if (!$enhancedSearch)
		{
			// get quickSearchTitle
			$quickSearchTitle = $this->filter('title', 'str');

			// check condition
			if (empty($quickSearchTitle))
			{
				return;
			}
			
			// if using (URL Portion) get nodeId
			if (empty($nodeId))
			{
				// get nodeName
				$nodeName = $params->node_name;

				// get result
				$finder = \XF::finder('XF:Node');
				$result = $finder
					->where('node_name', $nodeName)
					->fetchOne();

				// get title
				$nodeId = $result['node_id'];
			}

			// get threads
			$finder = \XF::finder('XF:Thread');
			$threads = $finder
				->where('thread_id', '<>', $currentThreadId)
				->where('node_id', $nodeId)
				->where('title', 'LIKE', $finder->escapeLike($quickSearchTitle, '%?%'))
				->where('discussion_state', '=', 'visible')
				->where('discussion_type', '<>', 'redirect')
				->order('post_date', 'DESC')
				->limit($maximumResults)
				->fetch()
				->filterViewable();

			// prepare viewParams
			$viewParams = [
				'threads' => $threads
			];

			// send to template
			return $this->view('Andy\QuickSearch:QuickSearch', 'andy_quicksearch', $viewParams);
		}
		
		// check condition
		if ($enhancedSearch)
		{
			// get quickSearchTitle
			$quickSearchTitle = $this->filter('title', 'str');

			// check condition
			if (empty($quickSearchTitle))
			{
				return;
			}

			// get options from Admin CP -> Options -> Quick search -> Miniumum common word length
			$minimumCommonWordLength = $options->quickSearchMinimumCommonWordLength;

			// get options from Admin CP -> Options -> Quick search -> Stop words
			$stopWords = $options->quickSearchStopWords;
			
			// get options from Admin CP -> Options -> Quick search -> Multibyte
			$multibyte = $options->quickSearchMultibyte;

			// get options from Admin CP -> Options -> Debug options -> xfesEnabled
			$xfesEnabled = @$options->xfesEnabled;

			// return error log
			if (!$xfesEnabled)
			{
				\XF::app()->error()->logError("Please deselect Enhanced search in Quick search options page.");
				return;
			}

			//########################################
			// define variables
			//########################################

			// set other variables
			$searchWords = array();
			$searchWord1 = '';
			$searchWord2 = '';
			$searchWord3 = '';
			
			$continue = true;
			$threadIds = array();
			$currentNodeId = $nodeId;
			
			$excludeThreadIds[] = $currentThreadId;

			//########################################
			// prepare search words
			//########################################

			if (!$multibyte)
			{
				// put into array
				$threadTitleArray = explode(' ', $quickSearchTitle);
			}

			if ($multibyte)
			{			
				// put into array
				$threadTitleArray = mb_split(' ', $quickSearchTitle);
			} 

			// create stopWordsArray
			$stopWordsArray = explode(' ', $stopWords);								

			// remove words from array
			foreach ($threadTitleArray as $var)
			{
				if (!$multibyte)
				{
					if (!in_array(strtolower($var), $stopWordsArray))
					{		
						// verify minimumCommonWordLength			
						if (strlen($var) >= $minimumCommonWordLength)
						{
							$searchWords[] = $var;
						}
					}
				}

				if ($multibyte)
				{
					if (!in_array(mb_strtolower($var), $stopWordsArray))
					{	
						// verify minimumCommonWordLength				
						if (mb_strlen($var) >= $minimumCommonWordLength)
						{
							$searchWords[] = $var;
						}
					}
				}
			}

			// get count
			$count = count($searchWords);

			// return in no searchWords
			if ($count == 0)
			{
				return;
			}			

			// continue we have a search word
			if ($count > 0)
			{				
				// get first search word
				$searchWord1 = $searchWords[0];

				if ($count > 1)
				{	
					// get second search word
					$searchWord2 = $searchWords[1];	
				}

				if ($count > 2)
				{	
					// get third search word
					$searchWord3 = $searchWords[2];	
				}							
			}

			//########################################
			// get eleasticsearch information
			//########################################

			// get configArray
			$configArray = $options->xfesConfig;

			// get host	
			$host = $configArray['host'];			

			// get port
			$port = $configArray['port'];

			// get https
			$https = $configArray['https'];

			// get index
			$index = $configArray['index'];

			// check condition
			if ($https)
			{
				$url = 'https://' . $host . ':' . $port . '/' . $index . '/_search';
			}
			else
			{
				$url = 'http://' . $host . ':' . $port . '/' . $index . '/_search';
			}

			//########################################
			// get excludeNodeIds
			//########################################

			if (!empty($excludeForums))
			{
				foreach ($excludeForums as $value)
				{
					$excludeNodeIds[] = $value;
				}
			}

			if (empty($excludeForums))
			{
				$excludeNodeIds[] = 0;
			}

			//########################################
			// search 1
			//########################################

			if ($count == 3)
			{
				$dsl =  
				array(
					"from" => 0, "size" => $maximumResults,
					"query" => array(
						"bool" => array(
							"must" => [
								array(
									"term" => array(
										"node" => $currentNodeId
									)
								),
								array(
									"term" => array(
										"type" => 'thread'
									)
								)
							],
							"filter" => array(
								"bool" => array(
									"must" => array(
										array(
											"term" => array(
												"type" => 'thread'
											)
										)
									),
									"must_not" => [
										array(
											"term" => array(
												"discussion_id" => $excludeThreadIds
											)
										),
										array(
											"terms" => array(
												"node" => $excludeNodeIds
											)
										),
										array(
											"term" => array(
												"hidden" => 'true'
											)
										)
									]
								)
							)
						)
					),
					"sort" => array(
						"date" => array("order" => "desc" )
					)
				);			

				// get json
				$json = json_encode($dsl);

				// check condition
				if (!empty($json))
				{
					// get client
					$client = \XF::app()->http()->client();

					try
					{
						$response = $client->request('POST', $url, [
							'exceptions' => false,
							'body' => $json,
							'headers' => [
								'Content-Type' => 'application/json',
								'Content-Length' => strlen($json)
							]
						]);
					}
					catch (\GuzzleHttp\Exception\RequestException $e)
					{
						if ($errorLog)
						{
							\XF::logException($e, false, "Similar threads error: ");
						}
						$continue = false;
					}

					// check condition
					if ($continue)
					{
						$results = $response->getBody()->getContents();
						$data = json_decode($results);

						if (!empty($data->_shards->total) AND $data->_shards->total > 0)
						{
							// get threadIds
							foreach ($data->hits->hits as $var) 
							{
								$var = str_replace('thread-', '', $var->_id);
								if (is_numeric($var))
								{
									$threadIds[] = $var;
								}
							}
						}
					}
				}
			}

			//########################################
			// search 2
			//########################################

			if ($count == 2)
			{
				$dsl = 
				array(
					"from" => 0, "size" => $maximumResults,
					"query" => array(
						"bool" => array(
							"must" => array(
								"match" => array(
									"title" => array(
										"query" => $searchWord1 . ' ' . $searchWord2,
										"operator" => "and"
									)
								)
							),
							"filter" => array(
								"bool" => array(
									"must" => [
										array(
											"term" => array(
												"node" => $currentNodeId
											)
										),
										array(
											"term" => array(
												"type" => 'thread'
											)
										)
									],
									"must_not" => [
										array(
											"terms" => array(
												"discussion_id" => $excludeThreadIds
											)
										),
										array(
											"terms" => array(
												"node" => $excludeNodeIds
											)
										),
										array(
											"term" => array(
												"hidden" => 'true'
											)
										)
									]                                      
								)
							)
						)
					),
					"sort" => array(
						"date" => array("order" => "desc" )
					)
				);			

				// get json
				$json = json_encode($dsl);

				// check condition
				if (!empty($json))
				{
					// get client
					$client = \XF::app()->http()->client();

					try
					{
						$response = $client->request('POST', $url, [
							'exceptions' => false,
							'body' => $json,
							'headers' => [
								'Content-Type' => 'application/json',
								'Content-Length' => strlen($json)
							]
						]);
					}
					catch (\GuzzleHttp\Exception\RequestException $e)
					{
						if ($errorLog)
						{
							\XF::logException($e, false, "Similar threads error: ");
						}
						$continue = false;
					}

					// check condition
					if ($continue)
					{
						$results = $response->getBody()->getContents();
						$data = json_decode($results);

						if (!empty($data->_shards->total) AND $data->_shards->total > 0)
						{
							// get threadIds
							foreach ($data->hits->hits as $var) 
							{
								$var = str_replace('thread-', '', $var->_id);
								if (is_numeric($var))
								{
									$threadIds[] = $var;
								}
							}
						}
					}
				}				
			}

			//########################################
			// search 3
			//########################################

			if ($count == 1)
			{
				$dsl = 
				array(
					"from" => 0, "size" => $maximumResults,
					"query" => array(
						"bool" => array(
							"must" => array(
								"match" => array(
									"title" => array(
										"query" => $searchWord1
									)
								)
							),
							"filter" => array(
								"bool" => array(
									"must" => [
										array(
											"term" => array(
												"node" => $currentNodeId
											)
										),
										array(
											"term" => array(
												"type" => 'thread'
											)
										)
									],
									"must_not" => [
										array(
											"terms" => array(
												"discussion_id" => $excludeThreadIds
											)
										),
										array(
											"terms" => array(
												"node" => $excludeNodeIds
											)
										),
										array(
											"term" => array(
												"hidden" => 'true'
											)
										)
									]                                      
								)
							)
						)
					),
					"sort" => array(
						"date" => array("order" => "desc" )
					)
				);

				// get json
				$json = json_encode($dsl);

				// check condition
				if (!empty($json))
				{
					// get client
					$client = \XF::app()->http()->client();

					try
					{
						$response = $client->request('POST', $url, [
							'exceptions' => false,
							'body' => $json,
							'headers' => [
								'Content-Type' => 'application/json',
								'Content-Length' => strlen($json)
							]
						]);
					}
					catch (\GuzzleHttp\Exception\RequestException $e)
					{
						if ($errorLog)
						{
							\XF::logException($e, false, "Similar threads error: ");
						}
						$continue = false;
					}

					// check condition
					if ($continue)
					{
						$results = $response->getBody()->getContents();
						$data = json_decode($results);

						if (!empty($data->_shards->total) AND $data->_shards->total > 0)
						{
							// get threadIds
							foreach ($data->hits->hits as $var) 
							{
								$var = str_replace('thread-', '', $var->_id);
								if (is_numeric($var))
								{
									$threadIds[] = $var;
								}
							}
						}
					}
				}			
			}

			//########################################
			// prepare viewParams
			//########################################

			// check condition
			if (empty($threadIds))
			{
				return;
			}

			// foreach condition
			foreach ($threadIds AS $threadId)
			{
				$conditions[] = ['thread_id', '=', $threadId];
			}

			// check condition
			if (!empty($conditions))
			{
				// get threads
				$finder = \XF::finder('XF:Thread');
				$threads = $finder
					->whereOr($conditions)
					->order('post_date', 'DESC')
					->fetch()
					->filterViewable();
			}
			else
			{
				$threads = array();
			}
			
			// prepare viewParams
			$viewParams = [
				'threads' => $threads
			]; 

			// send to template	
			return $this->view('Andy\QuickSearch:Index', 'andy_quicksearch', $viewParams);
		}
	}
}