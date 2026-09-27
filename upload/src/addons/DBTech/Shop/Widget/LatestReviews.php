<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Finder\ItemRatingFinder;
use DBTech\Shop\Repository\ItemRatingRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Http\Request;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class LatestReviews extends AbstractWidget
{
	/** @var array  */
	protected $defaultOptions = [
		'limit' => 5,
	];

	/**
	 * @return string|WidgetRenderer
	 */
	public function render(): string|WidgetRenderer
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewDbtechShopItems') || !$visitor->canViewDbtechShopItems())
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];

		/** @var ItemRatingFinder $finder */
		$finder = \XF::app()->repository(ItemRatingRepository::class)->findLatestReviews();
		$reviews = $finder->fetch(max($limit * 2, 10));

		/** @var ItemRating $review */
		foreach ($reviews AS $id => $review)
		{
			if (!$review->canView() || $review->isIgnored() || $review->Item->isIgnored())
			{
				unset($reviews[$id]);
			}
		}

		$total = $reviews->count();
		$reviews = $reviews->slice(0, $limit);

		$link = \XF::app()->router('public')->buildLink('dbtech-shop/latest-reviews');

		$viewParams = [
			'title' => $this->getTitle(),
			'link' => $link,
			'reviews' => $reviews,
			'hasMore' => $total > $reviews->count(),
		];
		return $this->renderer('dbtech_shop_widget_latest_reviews', $viewParams);
	}

	/**
	 * @param Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'limit' => 'uint',
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}