<?php

namespace XF\Cli\Command\Rebuild;

use XF\Job\ReactionScore;

class RebuildReactionScore extends AbstractRebuildCommand
{
	protected function getRebuildName(): string
	{
		return 'reaction-score';
	}

	protected function getRebuildDescription(): string
	{
		return 'Rebuilds user reaction score.';
	}

	protected function getRebuildClass(): string
	{
		return ReactionScore::class;
	}
}
