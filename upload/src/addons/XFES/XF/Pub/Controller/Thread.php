<?php

namespace XFES\XF\Pub\Controller;

class Thread extends XFCP_Thread
{
	/**
	 * @return array
	 */
	protected function getThreadViewExtraWith()
	{
		$with = parent::getThreadViewExtraWith();

		$with[] = 'XFES_SimilarThreads';

		return $with;
	}
}
