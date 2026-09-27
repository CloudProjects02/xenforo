<?php

namespace XF\Cli\Command\Rebuild;

use Symfony\Component\Console\Input\InputOption;
use XF\Job\Thread;

class RebuildThreads extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'threads';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds thread counters.';
	}

	protected function getRebuildClass()
	{
		return Thread::class;
	}

	protected function configureOptions()
	{
		$this
			->addOption(
				'position_rebuild',
				null,
				InputOption::VALUE_NONE,
				'Rebuild position and post counters. This will slow the process down and is only needed if posts are shown in an incorrect order or to show users when they have posted in a thread. Default: false'
			);
	}
}
