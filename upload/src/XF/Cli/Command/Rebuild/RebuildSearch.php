<?php

namespace XF\Cli\Command\Rebuild;

use Symfony\Component\Console\Input\InputOption;
use XF\Job\SearchRebuild;

class RebuildSearch extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'search';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds the search index.';
	}

	protected function getRebuildClass()
	{
		return SearchRebuild::class;
	}

	protected function configureOptions()
	{
		$this
			->addOption(
				'type',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Content type to rebuild search index for. Default: all'
			)
			->addOption(
				'truncate',
				null,
				InputOption::VALUE_NONE,
				'Delete the existing index before rebuilding. Default: false'
			);
	}
}
