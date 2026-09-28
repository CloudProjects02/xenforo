<?php

namespace DBTech\Shop\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

/**
 * Class Item
 *
 * @package DBTech\Shop\Report
 */
class Item extends AbstractHandler
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

		return (
			$visitor->hasDbtechShopCategoryPermission($categoryId, 'deleteAny')
			|| $visitor->hasDbtechShopCategoryPermission($categoryId, 'editAny')
		);
	}
	
	/**
	 * @param Report $report
	 * @param Entity $content
	 */
	public function setupReportEntityContent(Report $report, Entity $content)
	{
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $content;
		$category = $item->Category;
		
		if (!empty($item->prefix_id))
		{
			$title = $item->Prefix->title . ' - ' . $item->title;
		}
		else
		{
			$title = $item->title;
		}

		$report->content_user_id = $item->user_id;
		$report->content_info = [
			'item' => [
				'item_id' => $item->item_id,
				'title' => $title,
				'message' => $item->description,
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
		return \XF::phrase('dbtech_shop_item_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($report->content_info['category']['title'])
		]);
	}
	
	/**
	 * @param Report $report
	 *
	 * @return mixed
	 */
	public function getContentMessage(Report $report)
	{
		return $report->content_info['item']['message'];
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
			'canonical:dbtech-shop',
			[
				'item_id' => $info['item']['item_id'],
				'item_title' => $info['item']['title']
			]
		);
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Category'];
	}
}