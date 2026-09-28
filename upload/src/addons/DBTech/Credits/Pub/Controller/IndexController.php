<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\ControllerPlugin\Overview;
use DBTech\Credits\Pub\View;
use DBTech\Credits\XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;

class IndexController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechCredits())
		{
			throw $this->exception($this->noPermission());
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		/** @var Overview $overviewPlugin */
		$overviewPlugin = $this->plugin(Overview::class);

		$viewParams = $overviewPlugin->getCoreListData();

		$this->assertValidPage($viewParams['page'], $viewParams['perPage'], $viewParams['total'], 'dbtech-credits');
		$this->assertCanonicalUrl($this->buildLink('dbtech-credits', null, ['page' => $viewParams['page']]));

		return $this->view(
			View\OverviewView::class,
			'dbtech_credits_transactions',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): AbstractReply
	{
		/** @var Overview $overviewPlugin */
		$overviewPlugin = $this->plugin(Overview::class);

		return $overviewPlugin->actionFilters();
	}
}