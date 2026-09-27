<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\ItemRatingRepository;
use DBTech\Shop\Service\ItemRating\DeleteService;
use DBTech\Shop\XF\Entity\User;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\WarnPlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class ReviewController extends AbstractController
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

		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);

		return $this->redirectToReview($review);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
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

			$deleter = \XF::app()->service(DeleteService::class, $review);

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
			'item' => $review->Item,
		];
		return $this->view(
			View\ItemReview\DeleteView::class,
			'dbtech_shop_item_review_delete',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionUndelete(ParameterBag $params): AbstractReply
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params): AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);
		if (!$review->canReport($error))
		{
			return $this->noPermission($error);
		}

		$reportPlugin = $this->plugin(ReportPlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params): AbstractReply
	{
		$review = $this->assertViewableReview($params->item_rating_id);

		if (!$review->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$item = $review->Item;
		$breadcrumbs = $item->Category->getBreadcrumbs();

		$warnPlugin = $this->plugin(WarnPlugin::class);
		return $warnPlugin->actionWarn(
			'dbtech_shop_rating',
			$review,
			$this->buildLink('dbtech-shop/review/warn', $review),
			$breadcrumbs
		);
	}

	/**
	 * @param ItemRating $review
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 */
	protected function redirectToReview(ItemRating $review): AbstractReply
	{
		$item = $review->Item;

		$newerFinder = \XF::app()->repository(ItemRatingRepository::class)
			->findReviewsInItem($item)
		;
		$newerFinder->where('rating_date', '>', $review->rating_date);
		$totalNewer = $newerFinder->total();

		$perPage = \XF::app()->options()->dbtechShopReviewsPerPage;
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
	 * @return ItemRating
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableReview(?int $itemRatingId, array $extraWith = []): ItemRating
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Item';
		$extraWith[] = 'Item.User';
		$extraWith[] = 'Item.Category';
		$extraWith[] = 'Item.Category.Permissions|' . $visitor->permission_combination_id;

		$review = \XF::app()->em()->find(ItemRating::class, $itemRatingId, $extraWith);
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
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_items');
	}
}