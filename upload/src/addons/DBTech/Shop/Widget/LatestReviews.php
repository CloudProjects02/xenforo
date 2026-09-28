<?php

namespace DBTech\Shop\Widget;

use XF\Widget\AbstractWidget;

/**
 * Class LatestReviews
 *
 * @package DBTech\Shop\Widget
 */
class LatestReviews extends AbstractWidget
{
	/** @var array  */
	protected $defaultOptions = [
		'limit' => 5
	];
	
	/**
	 * @return string|\XF\Widget\WidgetRenderer
	 */
	public function render()
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewDbtechShopItems') || !$visitor->canViewDbtechShopItems())
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];

		/** @var \DBTech\Shop\Finder\ItemRating $finder */
		$finder = $this->repository('DBTech\Shop:ItemRating')->findLatestReviews();
		$reviews = $finder->fetch(max($limit * 2, 10));

		/** @var \DBTech\Shop\Entity\ItemRating $review */
		foreach ($reviews AS $id => $review)
		{
			if (!$review->canView() || $review->isIgnored() || $review->Item->isIgnored())
			{
				unset($reviews[$id]);
			}
		}

		$total = $reviews->count();
		$reviews = $reviews->slice(0, $limit);

		$link = $this->app->router('public')->buildLink('dbtech-shop/latest-reviews');

		$viewParams = [
			'title' => $this->getTitle(),
			'link' => $link,
			'reviews' => $reviews,
			'hasMore' => $total > $reviews->count()
		];
		return $this->renderer('dbtech_shop_widget_latest_reviews', $viewParams);
	}
	
	/**
	 * @param \XF\Http\Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'limit' => 'uint'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}