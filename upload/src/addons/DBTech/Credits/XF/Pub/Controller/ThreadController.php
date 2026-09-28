<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Pub\View\ContentAccess\ThreadView;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Thread;
use XF\InputFilterer;
use XF\Service\Thread\EditorService;

/**
 * @extends \XF\Pub\Controller\ThreadController
 */
class ThreadController extends XFCP_ThreadController
{
	/**
	 * @param \DBTech\Credits\XF\Entity\Thread $thread
	 *
	 * @return EditorService
	 * @throws \Exception
	 */
	protected function setupThreadEdit(Thread $thread)
	{
		/** @var \DBTech\Credits\XF\Service\Thread\EditorService $editor */
		$editor = parent::setupThreadEdit($thread);

		$cost = $this->filter('dbtech_credits_access_cost', InputFilterer::FLOAT);
		$currencyId = $this->filter('dbtech_credits_access_currency_id', InputFilterer::UNSIGNED);
		if ($cost && $currencyId)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

			$events = $eventTriggerRepo->getEventsForEventTrigger(
				'content_access',
				['node_id' => $thread->node_id]
			);
			$currencies = $currencyRepo->getCurrenciesFromEvents($events);

			if ($currencies->count() && $currencies->offsetExists($currencyId))
			{
				$editor->setDbtechCreditsAccessCost(
					$cost,
					$currencyId
				);
			}
		}
		else
		{
			$editor->setDbtechCreditsAccessCost(
				0.00,
				0
			);
		}

		return $editor;
	}

	/**
	 * @param int|null $threadId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Credits\XF\Entity\Thread
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'ContentAccessCurrency';
		if ($visitor->user_id)
		{
			$extraWith[] = 'ContentAccessPurchases|' . $visitor->user_id;
		}

		/** @var \DBTech\Credits\XF\Entity\Thread $thread */
		$thread = parent::assertViewableThread($threadId, $extraWith);

		if ($thread->dbtech_credits_access_cost > 0.00
			&& $thread->dbtech_credits_access_currency_id
			&& $thread->user_id !== $visitor->user_id
			&& !$visitor->is_staff
		)
		{
			// This thread requires payment to access, thread owner and staff can always access it
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

			$events = $eventTriggerRepo->getEventsForEventTrigger(
				'content_access',
				['node_id' => $thread->node_id]
			);
			$currencies = $currencyRepo->getCurrenciesFromEvents($events);
			if ($currencies->count() && $currencies->offsetExists($thread->dbtech_credits_access_currency_id))
			{
				if (!$visitor->user_id)
				{
					$this->assertRegistrationRequired();
				}
				else if (!$thread->ContentAccessPurchases[$visitor->user_id])
				{
					$viewParams = [
						'thread' => $thread,
						'forum' => $thread->Forum,
						'currency' => $thread->ContentAccessCurrency,
					];
					$view = $this->view(
						ThreadView::class,
						'dbtech_credits_content_access_thread',
						$viewParams
					);
					throw $this->exception($view);
				}
			}
		}

		return $thread;
	}
}