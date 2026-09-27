<?php

namespace XFRM\Pub\Controller;

use XF\ControllerPlugin\ContentVotePlugin;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\UndeletePlugin;
use XF\ControllerPlugin\WarnPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Mvc\RouteMatch;
use XF\Pub\Controller\AbstractController;
use XF\Pub\Controller\EmbedResolverTrait;
use XFRM\Entity\ResourceRating;
use XFRM\Service\ResourceRating\AuthorReply;
use XFRM\Service\ResourceRating\AuthorReplyDelete;
use XFRM\Service\ResourceRating\Delete;
use XFRM\XF\Entity\User;

class ResourceReview extends AbstractController
{
	use EmbedResolverTrait;

	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewResources($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		return $this->redirectToReview($review);
	}

	public function actionDelete(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);
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

			/** @var Delete $deleter */
			$deleter = $this->service('XFRM:ResourceRating\Delete', $review);

			if ($this->filter('author_alert', 'bool') && $review->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('resources', $review->Resource), false)
			);
		}
		else
		{
			$viewParams = [
				'review' => $review,
				'resource' => $review->Resource,
			];
			return $this->view('XFRM:ResourceReview\Delete', 'xfrm_resource_review_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		/** @var UndeletePlugin $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$review,
			$this->buildLink('resources/review/undelete', $review),
			$this->buildLink('resources/review', $review),
			\XF::phrase('xfrm_resource_review_in_x', ['title' => $review->resource_title]),
			'rating_state'
		);
	}

	public function actionReport(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);
		if (!$review->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var ReportPlugin $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'resource_rating',
			$review,
			$this->buildLink('resources/review/report', $review),
			$this->buildLink('resources/review', $review)
		);
	}

	public function actionVote(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		/** @var ContentVotePlugin $votePlugin */
		$votePlugin = $this->plugin('XF:ContentVote');

		return $votePlugin->actionVote(
			$review,
			$this->buildLink('resources/review', $review),
			$this->buildLink('resources/review/vote', $review)
		);
	}

	public function actionWarn(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		if (!$review->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$resource = $review->Resource;
		$breadcrumbs = $resource->Category->getBreadcrumbs();

		/** @var WarnPlugin $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'resource_rating',
			$review,
			$this->buildLink('resources/review/warn', $review),
			$breadcrumbs
		);
	}

	public function actionReply(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);

		if (!$review->canReply($error))
		{
			return $this->noPermission($error);
		}

		/** @var AuthorReply $authorReplier */
		$authorReplier = $this->service('XFRM:ResourceRating\AuthorReply', $review);

		$message = $this->filter('message', 'str');
		if (!$authorReplier->reply($message, $error))
		{
			return $this->error($error);
		}

		if ($this->filter('_xfWithData', 'bool'))
		{
			$viewParams = [
				'review' => $review,
				'resource' => $review->Resource,
			];
			return $this->view('XFRM:ResourceReview\ReplyAdded', 'xfrm_resource_review_reply_added', $viewParams);
		}
		else
		{
			return $this->redirect($this->buildLink('resources/review', $review));
		}
	}

	public function actionReplyDelete(ParameterBag $params)
	{
		$review = $this->assertViewableReview($params->resource_rating_id);
		if (!$review->canDeleteAuthorResponse($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			/** @var AuthorReplyDelete $deleter */
			$deleter = $this->service('XFRM:ResourceRating\AuthorReplyDelete', $review);
			$deleter->delete();

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('resources/review', $review), false)
			);
		}
		else
		{
			$viewParams = [
				'review' => $review,
				'resource' => $review->Resource,
			];
			return $this->view('XFRM:ResourceReview\ReplyDelete', 'xfrm_resource_review_reply_delete', $viewParams);
		}
	}

	protected function redirectToReview(ResourceRating $review)
	{
		$resource = $review->Resource;

		$newerFinder = $this->getRatingRepo()->findReviewsInResource($resource);
		$newerFinder->where('rating_date', '>', $review->rating_date);
		$totalNewer = $newerFinder->total();

		$perPage = $this->options()->xfrmReviewsPerPage;
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
			$this->buildLink('resources/reviews', $resource, $params)
			. '#resource-review-' . $review->resource_rating_id
		);
	}

	/**
	 * @param $resourceRatingId
	 * @param array $extraWith
	 *
	 * @return ResourceRating
	 *
	 * @throws Exception
	 */
	protected function assertViewableReview($resourceRatingId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Resource';
		$extraWith[] = 'Resource.User';
		$extraWith[] = 'Resource.Category';
		$extraWith[] = 'Resource.Category.Permissions|' . $visitor->permission_combination_id;

		/** @var ResourceRating $review */
		$review = $this->em()->find('XFRM:ResourceRating', $resourceRatingId, $extraWith);
		if (!$review)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfrm_requested_review_not_found')));
		}

		if (!$review->canView($error) || !$review->is_review)
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin('XFRM:Category')->applyCategoryContext($review->Resource->Category);

		return $review;
	}

	/**
	 * @return \XFRM\Repository\ResourceRating
	 */
	protected function getRatingRepo()
	{
		return $this->repository('XFRM:ResourceRating');
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('xfrm_viewing_resources');
	}

	public static function resolveToEmbeddableContent(ParameterBag $params, RouteMatch $routeMatch): ?Entity
	{
		$content = null;

		if ($params->resource_rating_id)
		{
			/** @var ResourceRating $content */
			$content = \XF::em()->find('XFRM:ResourceRating', $params->resource_rating_id);
		}

		if (!$content || !$content->canView())
		{
			$content = null;
		}

		return $content;
	}
}
