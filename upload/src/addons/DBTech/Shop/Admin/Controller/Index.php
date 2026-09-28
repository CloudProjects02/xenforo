<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;

/**
 * Class Index
 * @package DBTech\Shop\Admin\Controller
 */
class Index extends AbstractController
{
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		return $this->view('DBTech\Shop:Index', 'dbtech_shop');
	}
}