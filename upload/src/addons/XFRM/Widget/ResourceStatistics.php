<?php

namespace XFRM\Widget;

use XF\Widget\AbstractWidget;
use XFRM\XF\Entity\User;

class ResourceStatistics extends AbstractWidget
{
	public function render()
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewResources') || !$visitor->canViewResources())
		{
			return '';
		}

		$title = \XF::phrase('xfrm_resource_statistics');

		$viewParams = [
			'resourceStatistics' => $this->app->simpleCache()->XFRM->statisticsCache,
			'title' => $this->getTitle() ?: $title,
		];
		return $this->renderer('xfrm_widget_resource_statistics', $viewParams);
	}

	public function getOptionsTemplate(): void
	{
		return;
	}
}
