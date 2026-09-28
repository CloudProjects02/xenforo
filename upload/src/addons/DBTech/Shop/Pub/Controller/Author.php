<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Author
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Author extends AbstractController
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
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
	 */
	public function actionIndex(ParameterBag $params)
	{
		if ($params->user_id)
		{
			return $this->rerouteController('DBTech\Shop:Author', 'Author', $params);
		}

		/** @var \XF\Entity\MemberStat $memberStat */
		$memberStat = $this->em()->findOne('XF:MemberStat', ['member_stat_key' => 'dbtech_shop_most_items']);

		if ($memberStat && $memberStat->canView())
		{
			return $this->redirectPermanently(
				$this->buildLink('members', null, ['key' => $memberStat->member_stat_key])
			);
		}
		
		return $this->redirect($this->buildLink('dbtech-shop'));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAuthor(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var \DBTech\Shop\Repository\Category $categoryRepo */
		$categoryRepo = $this->repository('DBTech\Shop:Category');
		$viewableCategoryIds = $categoryRepo->getViewableCategoryIds();

		/** @var \DBTech\Shop\Repository\Item $itemRepo */
		$itemRepo = $this->repository('DBTech\Shop:Item');
		$finder = $itemRepo->findItemsByUser($user->user_id, $viewableCategoryIds);

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopItemsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/authors', $user);
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/authors', $user, ['page' => $page]));

		$items = $finder->limitByPage($page, $perPage)->fetch();
		$items = $items->filterViewable();

		$canInlineMod = false;
		foreach ($items AS $item)
		{
			/** @var \DBTech\Shop\Entity\Item $item */
			if ($item->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'user' => $user,
			'items' => $items,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'canInlineMod' => $canInlineMod
		];
		return $this->view('DBTech\Shop:Author\View', 'dbtech_shop_author_view', $viewParams);
	}
	
	/**
	 * @param array $activities
	 *
	 * @return array|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		$userIds = [];
		$userData = [];
		
		$router = \XF::app()->router('public');
		$defaultPhrase = \XF::phrase('dbtech_shop_viewing_author_profile');
		
		if (!\XF::visitor()->hasPermission('general', 'viewProfile'))
		{
			return $defaultPhrase;
		}
		
		foreach ($activities AS $activity)
		{
			$userId = $activity->pluckParam('user_id');
			if ($userId)
			{
				$userIds[$userId] = $userId;
			}
		}
		
		if ($userIds)
		{
			$users = \XF::em()->findByIds('XF:User', $userIds, 'Privacy');
			foreach ($users AS $user)
			{
				$userData[$user->user_id] = [
					'username' => $user->username,
					'url' => $router->buildLink('members', $user),
				];
			}
		}
		
		$output = [];
		
		foreach ($activities AS $key => $activity)
		{
			$userId = $activity->pluckParam('user_id');
			$user = $userId && isset($userData[$userId]) ? $userData[$userId] : null;
			if ($user)
			{
				$output[$key] = [
					'description' => \XF::phrase('dbtech_shop_viewing_author_profile'),
					'title' => $user['username'],
					'url' => $user['url']
				];
			}
			else
			{
				$output[$key] = $defaultPhrase;
			}
		}
		
		return $output;
	}
}