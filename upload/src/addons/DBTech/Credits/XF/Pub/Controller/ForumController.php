<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Forum;
use XF\InputFilterer;
use XF\Service\Thread\CreatorService;

class ForumController extends XFCP_ForumController
{
	/**
	 * @param Forum $forum
	 *
	 * @return CreatorService
	 * @throws \Exception
	 */
	protected function setupThreadCreate(Forum $forum)
	{
		/** @var \DBTech\Credits\XF\Service\Thread\CreatorService $creator */
		$creator = parent::setupThreadCreate($forum);

		$cost = $this->filter('dbtech_credits_access_cost', InputFilterer::FLOAT);
		$currencyId = $this->filter('dbtech_credits_access_currency_id', InputFilterer::UNSIGNED);
		if ($cost && $currencyId)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

			$events = $eventTriggerRepo->getEventsForEventTrigger(
				'content_access',
				['node_id' => $forum->node_id]
			);
			$currencies = $currencyRepo->getCurrenciesFromEvents($events);

			if ($currencies->count() && $currencies->offsetExists($currencyId))
			{
				$creator->setDbtechCreditsAccessCost(
					$cost,
					$currencyId
				);
			}
		}
		else
		{
			$creator->setDbtechCreditsAccessCost(
				0.00,
				0
			);
		}

		return $creator;
	}
}