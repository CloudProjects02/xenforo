<?php

namespace DBTech\Shop\EventTrigger;

use DBTech\Credits\Entity\Event;
use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Finder\EventFinder;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\User;
use XF\PrintableException;

class PurchaseHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'canCharge' => false,
			'useUserGroups' => false,

			'multiplier' => self::MULTIPLIER_LABEL,
		]);
	}

	/**
	 * @param Event $event
	 * @param User $user
	 * @param \ArrayObject $extraParams
	 * @param Transaction $transaction
	 *
	 * @return void
	 */
	protected function performApplyTransactionChecks(
		Event $event,
		User $user,
		\ArrayObject $extraParams,
		Transaction $transaction
	): void
	{
	}

	/**
	 * @param Event $event
	 * @param User $user
	 * @param \ArrayObject $extraParams
	 * @param Transaction $transaction
	 *
	 * @return void
	 */
	protected function performNegateTransactionChecks(
		Event $event,
		User $user,
		\ArrayObject $extraParams,
		Transaction $transaction
	): void
	{
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return string
	 */
	public function alertTemplate(Transaction $transaction): string
	{
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($which == 'spent')
		{
			return $this->getAlertPhrase('dbtech_shop_spent_x_y_on_purchase', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_shop_gained_x_y_from_purchase', $transaction);
		}
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}

	/**
	 * @return array
	 */
	public function getLabels(): array
	{
		$labels = parent::getLabels();

		$labels['minimum_amount'] = \XF::phrase('dbtech_shop_eventtrigger_point_minimum_amount');
		$labels['maximum_amount'] = \XF::phrase('dbtech_shop_eventtrigger_point_maximum_amount');
		$labels['minimum_action'] = \XF::phrase('dbtech_shop_eventtrigger_point_minimum_action');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_shop_eventtrigger_point_minimum_action_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_shop_eventtrigger_multiplier_point_addition');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_shop_eventtrigger_multiplier_point_addition_explain');
		$labels['multiplier_negation'] = \XF::phrase('dbtech_shop_eventtrigger_multiplier_point_negation');
		$labels['multiplier_negation_explain'] = \XF::phrase('dbtech_shop_eventtrigger_multiplier_point_negation_explain');

		return $labels;
	}

	/**
	 * @param int $currencyId
	 *
	 * @throws PrintableException
	 */
	protected function assertEventExists(int $currencyId = 0): void
	{
		if (!$currencyId)
		{
			return;
		}

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Event> $events */
		$events = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currencyId)
			->where('event_trigger_id', $this->getContentType())
			->fetch()
		;
		if ($events->count() > 1)
		{
			throw new \LogicException(
				"Multiple event definitions exist for DragonByte Shop event: " .
				\XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType())
			);
		}

		/** @var Event $event */
		$event = $events->first();
		if ($event)
		{
			// Making sure the event is set up correctly
			$event->bulkSet([
				'active'           => true,
				'main_add'         => 0,
				'mult_add'         => 1,
				'mult_sub'         => 1,
			]);
			$event->saveIfChanged($saved);

			if ($saved)
			{
				$this->setEvents();
			}
		}
		else
		{
			$event = \XF::app()->em()->create(Event::class);
			$event->bulkSet([
				'title'            => \XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType()),
				'active'           => true,
				'currency_id'      => $currencyId,
				'event_trigger_id' => $this->getContentType(),
				'main_add'         => 0,
				'mult_add'         => 1,
				'mult_sub'         => 1,
			]);
			$event->save();

			$this->setEvents();
		}
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @throws PrintableException
	 */
	protected function postSave(Transaction $transaction): void
	{
		$purchase = \XF::app()->em()->find(Purchase::class, $transaction->reference_id);
		if (!$purchase)
		{
			return;
		}

		$purchaseRepo = \XF::app()->repository(PurchaseRepository::class);
		$purchaseRepo->logTransaction(
			$purchase,
			'purchase',
			$transaction->amount,
			$transaction->TargetUser
		);
	}
}