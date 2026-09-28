<?php

namespace XFRM\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractImageOptimizationCommand;

class IconOptimization extends AbstractImageOptimizationCommand
{
	protected function getRebuildName(): string
	{
		return 'xfrm-icon-optimization';
	}

	protected function getRebuildDescription(): string
	{
		return 'Optimizes icons to WebP format.';
	}

	protected function getRebuildClass(): string
	{
		return \XFRM\Job\IconOptimization::class;
	}
}
