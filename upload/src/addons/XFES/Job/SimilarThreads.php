<?php

namespace XFES\Job;

use XF\Job\AbstractRebuildJob;
use XF\Job\JobResult;
use XF\Phrase;
use XFES\XF\Repository\Thread;

class SimilarThreads extends AbstractRebuildJob
{
	protected $failureLogged = false;

	/**
	 * @param int $maxRunTime
	 *
	 * @return JobResult
	 */
	public function run($maxRunTime)
	{
		if (!$this->app->options()->xfesEnabled)
		{
			return $this->complete();
		}

		return parent::run($maxRunTime);
	}

	/**
	 * @param int $start
	 * @param int $batch
	 *
	 * @return int[]
	 */
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'SELECT thread_id
					FROM xf_es_thread_similar
					WHERE thread_id > ? AND pending_rebuild = 1
					ORDER BY thread_id',
				$batch
			),
			$start
		);
	}

	/**
	 * @param int $id
	 */
	protected function rebuildById($id)
	{
		$thread = $this->app->find('XF:Thread', $id, ['XFES_SimilarThreads']);
		if (!$thread)
		{
			return;
		}

		/** @var Thread $threadRepo */
		$threadRepo = $this->app->repository('XF:Thread');

		try
		{
			$threadRepo->rebuildSimilarThreadsCache($thread);
		}
		catch (\Exception $e)
		{
			if (!$this->failureLogged)
			{
				\XF::logException($e, false, "Similar thread cache rebuild failure: ");
				$this->failureLogged = true;
			}
		}
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType()
	{
		return \XF::phrase('xfes_similar_threads');
	}
}
