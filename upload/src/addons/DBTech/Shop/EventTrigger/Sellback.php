<?php

namespace DBTech\Shop\EventTrigger;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Entity\Transaction;

/**
 * Class Sellback
 *
 * @package DBTech\Credits\EventTrigger
 */
class Sellback extends AbstractHandler
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
			
			'multiplier' => self::MULTIPLIER_LABEL
		]);
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
			return $this->getAlertPhrase('dbtech_shop_spent_x_y_on_sellback', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_shop_gained_x_y_from_sellback', $transaction);
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
	 * @throws \XF\PrintableException
	 */
	protected function assertEventExists(int $currencyId = 0)
	{
		if (!$currencyId)
		{
			return;
		}
		
		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = $this->finder('DBTech\Credits:Event')
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
		
		/** @var \DBTech\Credits\Entity\Event $event */
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
				$this->setEvents(null);
			}
		}
		else
		{
			/** @var \DBTech\Credits\Entity\Event $event */
			$event = $this->em()->create('DBTech\Credits:Event');
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
			
			$this->setEvents(null);
		}
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @throws \XF\PrintableException
	 */
	protected function postSave(Transaction $transaction): void
	{
		/** @var \DBTech\Shop\Entity\Purchase $purchase */
		$purchase = $this->em()->find('DBTech\Shop:Purchase', $transaction->reference_id);
		if (!$purchase)
		{
			return;
		}
		
		$purchaseRepo = \XF::repository('DBTech\Shop:Purchase');
		$purchaseRepo->logTransaction(
			$purchase,
			'sellback',
			$transaction->amount,
			$transaction->TargetUser
		);
	}
}