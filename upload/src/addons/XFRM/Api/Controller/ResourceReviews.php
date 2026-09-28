<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XFRM\Entity\ResourceItem;
use XFRM\Entity\ResourceRating;
use XFRM\Service\ResourceItem\Rate;

class ResourceReviews extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource_rating');
	}

	public function actionGet()
	{
		$ratingRepo = $this->repository('XFRM:ResourceRating');
		$finder = $ratingRepo->findLatestReviewsForApi();

		$total = $finder->total();
		$page = $this->filterPage();
		$perPage = $this->options()->xfrmReviewsPerPage;

		$this->assertValidApiPage($page, $perPage, $total);

		$reviews = $finder->limitByPage($page, $perPage)->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$reviews = $reviews->filterViewable();
		}

		return $this->apiResult([
			'reviews' => $reviews->toApiResults(Entity::VERBOSITY_NORMAL, ['with_resource' => true]),
			'pagination' => $this->getPaginationData($reviews, $page, $perPage, $total),
		]);
	}

	public function actionPost()
	{
		$this->assertRequiredApiInput(['resource_id', 'rating']);

		$resourceId = $this->filter('resource_id', 'uint');

		/** @var ResourceItem $resource */
		$resource = $this->assertViewableApiRecord('XFRM:ResourceItem', $resourceId);

		if (\XF::isApiCheckingPermissions())
		{
			if (!$resource->canRate(true, $error))
			{
				return $this->noPermission($error);
			}

			/** @var ResourceRating|null $existingRating */
			$existingRating = $resource->CurrentVersion->Ratings[\XF::visitor()->user_id];
			if ($existingRating && !$existingRating->canUpdate($error))
			{
				return $this->noPermission($error);
			}
		}

		$rater = $this->setupResourceRate($resource);

		if (\XF::isApiCheckingPermissions())
		{
			$rater->checkForSpam();
		}

		if (!$rater->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var ResourceRating $review */
		$review = $rater->save();

		return $this->apiSuccess([
			'review' => $review->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param ResourceItem $resource
	 *
	 * @return Rate
	 */
	protected function setupResourceRate(ResourceItem $resource)
	{
		/** @var Rate $rater */
		$rater = $this->service('XFRM:ResourceItem\Rate', $resource);

		$input = $this->filter([
			'rating' => 'uint',
			'message' => 'str',
			'is_anonymous' => 'bool',
		]);

		$rater->setRating($input['rating'], $input['message']);

		if ($this->options()->xfrmAllowAnonReview && $input['is_anonymous'])
		{
			$rater->setIsAnonymous();
		}

		return $rater;
	}
}
