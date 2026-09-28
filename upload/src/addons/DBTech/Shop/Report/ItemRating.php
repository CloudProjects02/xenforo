<?php

namespace DBTech\Shop\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\Report
 */
class ItemRating extends AbstractHandler
{
	/**
	 * @param Report $report
	 *
	 * @return bool
	 */
	protected function canViewContent(Report $report): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$categoryId = $report->content_info['item']['category_id'];
		$itemId = $report->content_info['item']['item_id'];
		
		if (!method_exists($visitor, 'hasDbtechShopCategoryPermission'))
		{
			return false;
		}
		
		if (!method_exists($visitor, 'hasDbtechShopItemPermission'))
		{
			return false;
		}

		return (
			$visitor->hasDbtechShopCategoryPermission($categoryId, 'view')
			&& $visitor->hasDbtechShopItemPermission($itemId, 'view')
		);
	}
	
	/**
	 * @param Report $report
	 *
	 * @return bool
	 */
	protected function canActionContent(Report $report): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$categoryId = $report->content_info['item']['category_id'];

		if (!method_exists($visitor, 'hasDbtechShopCategoryPermission'))
		{
			return false;
		}

		return $visitor->hasDbtechShopCategoryPermission($categoryId, 'deleteAnyReview');
	}
	
	/**
	 * @param Report $report
	 * @param Entity $content
	 */
	public function setupReportEntityContent(Report $report, Entity $content)
	{
		/** @var \DBTech\Shop\Entity\ItemRating $rating */
		$rating = $content;
		$item = $rating->Item;
		$category = $item->Category;
		
		if (!empty($item->prefix_id))
		{
			$title = $item->Prefix->title . ' - ' . $item->title;
		}
		else
		{
			$title = $item->title;
		}

		$report->content_user_id = $rating->user_id;
		$report->content_info = [
			'rating' => [
				'item_rating_id' => $rating->item_rating_id,
				'item_id' => $rating->item_id,
				'rating' => $rating->rating,
				'message' => $rating->message
			],
			'item' => [
				'item_id' => $item->item_id,
				'title' => $title,
				'prefix_id' => $item->prefix_id,
				'category_id' => $item->category_id,
				'user_id' => $item->user_id,
				'username' => $item->username
			],
			'category' => [
				'category_id' => $category->category_id,
				'title' => $category->title
			]
		];
	}
	
	/**
	 * @param Report $report
	 *
	 * @return \XF\Phrase
	 */
	public function getContentTitle(Report $report): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_item_review_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($report->content_info['item']['title'])
		]);
	}
	
	/**
	 * @param Report $report
	 *
	 * @return mixed
	 */
	public function getContentMessage(Report $report)
	{
		return $report->content_info['rating']['message'];
	}
	
	/**
	 * @param Report $report
	 *
	 * @return mixed|string
	 */
	public function getContentLink(Report $report): string
	{
		$info = $report->content_info;

		return \XF::app()->router()->buildLink(
			'canonical:dbtech-shop/review',
			[
				'item_id' => $info['item']['item_id'],
				'item_title' => $info['item']['title'],
				'item_rating_id' => $info['rating']['item_rating_id']
			]
		);
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Item', 'Item.Category'];
	}
}