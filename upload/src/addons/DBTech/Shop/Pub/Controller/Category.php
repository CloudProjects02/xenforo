<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Category
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Category extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$category = $this->assertViewableCategory($params->category_id, $this->getCategoryViewExtraWith());

		/** @var \DBTech\Shop\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Shop:Overview');

		$categoryParams = $overviewPlugin->getCategoryListData($category);

		/** @var \XF\Tree $categoryTree */
		$categoryTree = $categoryParams['categoryTree'];
		$descendants = $categoryTree->getDescendants($category->category_id);

		$sourceCategoryIds = array_keys($descendants);
		$sourceCategoryIds[] = $category->category_id;

		// for any contextual widget
		$category->cacheViewableDescendents($descendants);

		$listParams = $overviewPlugin->getCoreListData($sourceCategoryIds);

		$this->assertValidPage(
			$listParams['page'],
			$listParams['perPage'],
			$listParams['total'],
			'dbtech-shop/categories',
			$category
		);
		$this->assertCanonicalUrl($this->buildLink(
			'dbtech-shop/categories',
			$category,
			['page' => $listParams['page']]
		));

		$viewParams = [
			'category' => $category,
			'pendingApproval' => $this->filter('pending_approval', 'bool')
		];
		$viewParams += $categoryParams + $listParams;

		return $this->view('DBTech\Shop:Category\View', 'dbtech_shop_category_view', $viewParams);
	}
	
	/**
	 * @return array
	 */
	protected function getCategoryViewExtraWith(): array
	{
		$extraWith = [];
		$userId = \XF::visitor()->user_id;
		if ($userId)
		{
			$extraWith[] = 'Watch|' . $userId;
		}

		return $extraWith;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionFilters(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->category_id);

		/** @var \DBTech\Shop\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Shop:Overview');

		return $overviewPlugin->actionFilters($category);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionWatch(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$category = $this->assertViewableCategory($params->category_id);
		if (!$category->canWatch($error))
		{
			return $this->noPermission($error);
		}

		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			if ($this->filter('stop', 'bool'))
			{
				$action = 'delete';
				$config = [];
			}
			else
			{
				$action = 'watch';
				$config = $this->filter([
					'notify_on' => 'str',
					'send_alert' => 'bool',
					'send_email' => 'bool',
					'include_children' => 'bool'
				]);
			}

			/** @var \DBTech\Shop\Repository\CategoryWatch $watchRepo */
			$watchRepo = $this->repository('DBTech\Shop:CategoryWatch');
			$watchRepo->setWatchState($category, $visitor, $action, $config);

			$redirect = $this->redirect($this->buildLink('dbtech-shop/categories', $category));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		
		$viewParams = [
			'category' => $category,
			'isWatched' => !empty($category->Watch[$visitor->user_id])
		];
		return $this->view('DBTech\Shop:Category\Watch', 'dbtech_shop_category_watch', $viewParams);
	}

	/**
	 * @param int|null $categoryId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory(?int $categoryId, array $extraWith = []): \DBTech\Shop\Entity\Category
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;

		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $this->em()->find('DBTech\Shop:Category', $categoryId, $extraWith);
		if (!$category)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_category_not_found')));
		}

		if (!$category->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $category;
	}
	
	/**
	 * @param array $activities
	 *
	 * @return array|bool
	 */
	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities,
			\XF::phrase('dbtech_shop_viewing_item_category'),
			'category_id',
			function (array $ids): array
			{
				$categories = \XF::em()->findByIds(
					'DBTech\Shop:Category',
					$ids,
					['Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($categories->filterViewable() AS $id => $category)
				{
					$data[$id] = [
						'title' => $category->title,
						'url' => $router->buildLink('dbtech-shop/categories', $category)
					];
				}

				return $data;
			}
		);
	}
}