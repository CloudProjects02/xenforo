<?php

namespace XF\Cli\Command\Rebuild;

use XF\Job\IconUsage;

class RebuildIconUsage extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'icon-usage';
	}

	protected function getRebuildDescription()
	{
		return 'Analyzes icon usage and rebuilds sprites.';
	}

	protected function getRebuildClass()
	{
		return IconUsage::class;
	}
}
