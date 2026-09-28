<?php

namespace XFRM\Cron;

use XFRM\Repository\ResourceItem;

class Views
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var ResourceItem $resourceRepo */
		$resourceRepo = $app->repository('XFRM:ResourceItem');
		$resourceRepo->batchUpdateResourceViews();
	}
}
