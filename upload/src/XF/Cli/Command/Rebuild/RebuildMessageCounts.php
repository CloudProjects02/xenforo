<?php

namespace XF\Cli\Command\Rebuild;

use XF\Job\MessageCount;

class RebuildMessageCounts extends AbstractRebuildCommand
{
	protected function getRebuildName(): string
	{
		return 'message-counts';
	}

	protected function getRebuildDescription(): string
	{
		return 'Rebuilds user message counts.';
	}

	protected function getRebuildClass(): string
	{
		return MessageCount::class;
	}
}
