<?php

namespace DBTech\Shop\Report;

use DBTech\Shop\Entity\TradePost;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Report\AbstractHandler;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @param Report $report
	 *
	 * @return bool
	 */
	protected function canViewContent(Report $report): bool
	{
		/** @var TradePost $content */
		$content = $report->Content;
		return $content->canView();
	}

	/**
	 * @param Report $report
	 *
	 * @return bool
	 */
	protected function canActionContent(Report $report): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->hasPermission('dbtechShopTradePost', 'editAny') || $visitor->hasPermission('dbtechShopTradePost', 'deleteAny'));
	}

	/**
	 * @param Report $report
	 * @param Entity $content
	 */
	public function setupReportEntityContent(Report $report, Entity $content): void
	{
		/** @var TradePost $content */
		$report->content_user_id = $content->user_id;
		$report->content_info = [
			'message' => $content->message,
			'tradeOwner' => [
				'user_id' => $content->Trade->creator_user_id,
				'username' => $content->Trade->creator_username,
			],
			'user' => [
				'user_id' => $content->user_id,
				'username' => $content->username,
			],
			'trade_post_id' => $content->trade_post_id,
			'trade_id' => $content->trade_id,
		];
	}

	/**
	 * @param Report $report
	 *
	 * @return Phrase
	 */
	public function getContentTitle(Report $report): Phrase
	{
		if (isset($report->content_info['user']))
		{
			return \XF::phrase('dbtech_shop_trade_post_by_x', [
				'name' => $report->content_info['user']['username'],
			]);
		}
		else
		{
			return \XF::phrase('dbetch_shop_trade_post_in_x', [
				'tradeId' => $report->content_info['trade_id'],
			]);
		}
	}

	/**
	 * @param Report $report
	 *
	 * @return mixed
	 */
	public function getContentMessage(Report $report): mixed
	{
		return $report->content_info['message'];
	}

	/**
	 * @param Report $report
	 *
	 * @return string
	 */
	public function getContentLink(Report $report): string
	{
		return \XF::app()->router('public')->buildLink('canonical:dbtech-shop/trade-posts', ['trade_post_id' => $report->content_id]);
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}