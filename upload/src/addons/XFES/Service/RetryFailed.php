<?php

namespace XFES\Service;

use XF\App;
use XF\Service\AbstractService;
use XFES\Elasticsearch\Api;
use XFES\Entity\IndexFailed;

class RetryFailed extends AbstractService
{
	protected $es;

	public function __construct(App $app, Api $es)
	{
		parent::__construct($app);

		$this->es = $es;
	}

	public function retry($maxRunTime = null)
	{
		/** @var \XFES\Repository\IndexFailed $indexFailedRepo */
		$indexFailedRepo = $this->repository('XFES:IndexFailed');
		$searcher = $this->app->search();

		$start = microtime(true);

		$failures = $indexFailedRepo->findRetryableRecords()->fetch(1000);
		foreach ($failures AS $failure)
		{
			/** @var IndexFailed $failure */
			$type = $failure->content_type;
			$id = $failure->content_id;

			if (!$searcher->isValidContentType($type))
			{
				$this->handleFailure($failure);
				continue;
			}

			try
			{
				if ($failure->action == 'delete')
				{
					$this->es->delete($type, $id);
				}
				else if ($failure->action == 'index')
				{
					$this->es->index($type, $id, $failure->data);
				}

				// if reached, action has been successful
				$failure->delete();
			}
			catch (\XFES\Elasticsearch\Exception $e)
			{
				$this->handleFailure($failure, $e);
			}

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				break;
			}
		}
	}

	protected function handleFailure(IndexFailed $failure, ?\Exception $e = null)
	{
		if ($failure->fail_count >= 5)
		{
			$type = $failure->content_type;
			$id = $failure->content_id;

			$failure->delete();

			// no exception indicates that the content type is no longer valid, skip logging
			if ($e)
			{
				\XF::logException($e, false, "Indexing $type:$id failed 5 times (skipping): ");
			}
		}
		else
		{
			// will delay by 1, 2, 4, 8, 16 hours
			$failure->reindex_date = \XF::$time + 3600 * (2 ** $failure->fail_count);
			$failure->fail_count++;
			$failure->save();
		}
	}
}
