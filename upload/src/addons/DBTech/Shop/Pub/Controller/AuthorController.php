<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemRepository;
use XF\Entity\MemberStat;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Phrase;
use XF\Pub\Controller\AbstractController;

class AuthorController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
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
	 * @return AbstractReply
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->user_id)
		{
			return $this->rerouteController(AuthorController::class, 'Author', $params);
		}

		$memberStat = \XF::app()->em()->findOne(MemberStat::class, ['member_stat_key' => 'dbtech_shop_most_items']);

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
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws Exception
	 */
	public function actionAuthor(ParameterBag $params): AbstractReply
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */
		$user = $this->assertRecordExists(User::class, $params->user_id);

		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$viewableCategoryIds = $categoryRepo->getViewableCategoryIds();

		$itemRepo = \XF::app()->repository(ItemRepository::class);
		$finder = $itemRepo->findItemsByUser($user->user_id, $viewableCategoryIds);

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopItemsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/authors', $user);
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/authors', $user, ['page' => $page]));

		$items = $finder->limitByPage($page, $perPage)->fetch();
		$items = $items->filterViewable();

		$canInlineMod = false;
		foreach ($items AS $item)
		{
			/** @var Item $item */
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
			'canInlineMod' => $canInlineMod,
		];
		return $this->view(
			View\Author\ViewView::class,
			'dbtech_shop_author_view',
			$viewParams
		);
	}

	/**
	 * @param array $activities
	 *
	 * @return array|Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase|array
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
			$users = \XF::app()->em()->findByIds(User::class, $userIds, ['Privacy']);
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
					'url' => $user['url'],
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