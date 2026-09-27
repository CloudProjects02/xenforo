<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Reply\AbstractReply;

class IndexController extends AbstractController
{
	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\IndexView::class,
			'dbtech_shop'
		);
	}
}