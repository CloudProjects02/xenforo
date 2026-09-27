<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Finder\EventFinder;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Entity\AbstractCollection;
use XF\PrintableException;

class PaymentHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => false,
			'canCharge' => true,
			'canCancel' => true,
			'useUserGroups' => false,

			'multiplier' => self::MULTIPLIER_LABEL,
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		$purchasable = null;
		$purchasableItem = null;

		if ($transaction->content_id)
		{
			$purchaseRequest = \XF::app()->em()->find(
				PurchaseRequest::class,
				$transaction->content_id,
				[
					'PaymentProfile',
					'Purchasable',
					'User',
				]
			)
			;
			if ($purchaseRequest)
			{
				$purchasable = $purchaseRequest->Purchasable;
				$purchasableItem = $purchasable->handler->getPurchasableFromExtraData($purchaseRequest->extra_data);
			}
		}

		$params = [
			'item' => $purchasableItem ? $purchasableItem['title'] : \XF::phrase('n_a'),
		];

		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($which == 'spent')
		{
			return $this->getAlertPhrase('dbtech_credits_spent_x_y_via_payment', $transaction, $params);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_payment', $transaction, $params);
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

		$labels['minimum_amount'] = \XF::phrase('dbtech_credits_event_multmin_currency');
		$labels['maximum_amount'] = \XF::phrase('dbtech_credits_event_multmax_currency');
		$labels['minimum_action'] = \XF::phrase('dbtech_credits_event_minaction_currency');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_event_minaction_currency_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_event_mult_add_currency');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_event_mult_add_currency_explain');

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

		/** @var AbstractCollection<EventEntity> $events */
		$events = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currencyId)
			->where('event_trigger_id', $this->getContentType())
			->fetch()
		;
		if ($events->count() > 1)
		{
			throw new \LogicException(
				"Multiple event definitions exist for DragonByte Credits event: " .
				\XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType())
			);
		}

		/** @var EventEntity $event */
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
			$event = \XF::app()->em()->create(EventEntity::class);
			$event->bulkSet([
				'title'            => \XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType()),
				'active'           => true,
				'currency_id'      => $currencyId,
				'event_trigger_id' => $this->getContentType(),
				'charge'           => true,
				'main_add'         => 0,
				'mult_add'         => 1,
				'mult_sub'         => 1,
			]);
			$event->save();

			$this->setEvents();
		}
	}
}