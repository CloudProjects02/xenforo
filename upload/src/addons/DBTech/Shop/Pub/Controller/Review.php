<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Review
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Review extends AbstractController
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
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$review = $this->assertViewableReview($params->item_rating_id);

		return $this->redirectToReview($review);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDelete(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);
		if (!$review->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$review->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \DBTech\Shop\Service\ItemRating\Delete $deleter */
			$deleter = $this->service('DBTech\Shop:ItemRating\Delete', $review);

			if ($this->filter('author_alert', 'bool') && $review->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('dbtech-shop', $review->Item), false)
			);
		}
		
		$viewParams = [
			'review' => $review,
			'item' => $review->Item
		];
		return $this->view('DBTech\Shop:ItemReview\Delete', 'dbtech_shop_item_review_delete', $viewParams);
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionUndelete(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$review = $this->assertViewableReview($params->item_rating_id);
		if (!$review->canUndelete($error))
		{
			return $this->noPermission($error);
		}

		if ($review->rating_state == 'deleted')
		{
			$review->rating_state = 'visible';
			$review->save();
		}

		return $this->redirect($this->buildLink('dbtech-shop/review', $review));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);
		if (!$review->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'dbtech_shop_rating',
			$review,
			$this->buildLink('dbtech-shop/review/report', $review),
			$this->buildLink('dbtech-shop/review', $review)
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);

		if (!$review->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$item = $review->Item;
		$breadcrumbs = $item->Category->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'dbtech_shop_rating',
			$review,
			$this->buildLink('dbtech-shop/review/warn', $review),
			$breadcrumbs
		);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\ItemRating $review
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \InvalidArgumentException
	 */
	protected function redirectToReview(\DBTech\Shop\Entity\ItemRating $review): \XF\Mvc\Reply\Redirect
	{
		$item = $review->Item;

		$newerFinder = $this->getRatingRepo()->findReviewsInItem($item);
		$newerFinder->where('rating_date', '>', $review->rating_date);
		$totalNewer = $newerFinder->total();

		$perPage = $this->options()->dbtechShopReviewsPerPage;
		$page = ceil(($totalNewer + 1) / $perPage);

		if ($page > 1)
		{
			$params = ['page' => $page];
		}
		else
		{
			$params = [];
		}

		return $this->redirect(
			$this->buildLink('dbtech-shop/reviews', $item, $params)
			. '#item-review-' . $review->item_rating_id
		);
	}

	/**
	 * @param int|null $itemRatingId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\ItemRating
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableReview(?int $itemRatingId, array $extraWith = []): \DBTech\Shop\Entity\ItemRating
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Item';
		$extraWith[] = 'Item.User';
		$extraWith[] = 'Item.Category';
		$extraWith[] = 'Item.Category.Permissions|' . $visitor->permission_combination_id;

		/** @var \DBTech\Shop\Entity\ItemRating $review */
		$review = $this->em()->find('DBTech\Shop:ItemRating', $itemRatingId, $extraWith);
		if (!$review)
		{
			throw $this->exception($this->notFound(\XF::phrase('dbtech_shop_requested_review_not_found')));
		}

		$error = null;
		if (!$review->is_review || !$review->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $review;
	}

	/**
	 * @return \DBTech\Shop\Repository\ItemRating|\XF\Mvc\Entity\Repository
	 */
	protected function getRatingRepo()
	{
		return $this->repository('DBTech\Shop:ItemRating');
	}
	
	/**
	 * @param array $activities
	 *
	 * @return bool|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('dbtech_shop_viewing_items');
	}
}