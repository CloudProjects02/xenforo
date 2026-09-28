<?php

namespace XFES\Cron;

use XFES\Listener;
use XFES\Service\RetryFailed;

class Reindex
{
	public static function reindex()
	{
		if (\XF::options()->xfesEnabled)
		{
			/** @var RetryFailed $retrier */
			$retrier = \XF::service('XFES:RetryFailed', Listener::getElasticsearchApi());
			$retrier->retry(\XF::config('jobMaxRunTime'));
		}
	}
}
