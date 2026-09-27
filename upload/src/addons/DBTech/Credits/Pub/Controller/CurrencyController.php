<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\Entity\Charge;
use DBTech\Credits\Entity\ChargePurchase;
use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Entity\Event;
use DBTech\Credits\EventTrigger\AdjustHandler;
use DBTech\Credits\EventTrigger\DonateHandler;
use DBTech\Credits\EventTrigger\PurchaseHandler;
use DBTech\Credits\EventTrigger\TransferHandler;
use DBTech\Credits\Finder\ChargeFinder;
use DBTech\Credits\Finder\EventFinder;
use DBTech\Credits\Pub\View;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Db\DuplicateKeyException;
use XF\Entity\LinkableInterface;
use XF\Entity\PaymentProfile;
use XF\Entity\User;
use XF\InputFilterer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ExceptionAlias;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Repository\PaymentRepository;
use XF\Repository\UserRepository;

class CurrencyController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws ExceptionAlias
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechCredits())
		{
			throw $this->exception($this->noPermission());
		}

		if ($action == 'BuyContent')
		{
			$this->assertRegistrationRequired();
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$events = null;
		$transferCurrencies = [];

		if ($visitor->user_id)
		{
			/** @var EventFinder $eventFinder */
			$eventFinder = \XF::app()->finder(EventFinder::class)
				->where('currency_id', $currency->currency_id)
				->where('event_trigger_id', [
					'donate', 'adjust', 'purchase', 'redeem', 'transfer',
				])
			;

			/** @var AbstractCollection<Event> $events */
			$events = $eventFinder
				->fetch()
				->filter(function (Event $event) use ($currency, $visitor): bool
				{
					if (!$event->isActive())
					{
						return false;
					}

					if (!$event->getEventTriggerHandler()->isActive())
					{
						return false;
					}

					switch ($event->event_trigger_id)
					{
						case 'adjust':
							if (!$visitor->canAdjustDbtechCreditsCurrencies())
							{
								return false;
							}
							break;

						case 'transfer':
							if (!$currency->outbound)
							{
								return false;
							}

							if (!$event->Transfers->count())
							{
								return false;
							}
							break;
					}

					return true;
				})
			;

			foreach ($events AS $event)
			{
				if ($event->event_trigger_id == 'transfer')
				{
					foreach ($event->Transfers AS $inboundTransfer)
					{
						$transferCurrencies[$inboundTransfer->currency_id] = $inboundTransfer->Currency;
					}
				}
			}
		}

		$user = null;

		$userId = $this->filter('user_id', InputFilterer::UNSIGNED);
		if ($userId)
		{
			$user = $this->assertUserExists($userId);
		}

		$paymentRepo = \XF::app()->repository(PaymentRepository::class);
		/** @var AbstractCollection<PaymentProfile> $profiles */
		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$profileThirdParties = [];
		foreach ($profiles AS $profileId => $profile)
		{
			$profileId = (int) $profileId;

			if (!$profile->active)
			{
				unset($profiles[$profileId]);
				continue;
			}

			if (\XF::$versionId >= 2021270)
			{
				$profileThirdParties = array_merge(
					$profileThirdParties,
					$profile->Provider->getCookieThirdParties()
				);
			}
		}

		if (\XF::$versionId >= 2021270)
		{
			$this->assertCookieConsent([], array_unique($profileThirdParties));
		}

		$viewParams = [
			'currency' => $currency,
			'eventTriggers' => $events ? $events->pluckNamed('event_trigger_id', 'event_trigger_id') : [],
			'events' => $events,
			'transferCurrencies' => $transferCurrencies,
			'user' => $user,
			'profiles' => $profiles,
			'tab' => $this->filter('tab', InputFilterer::STRING),
		];
		return $this->view(
			View\Currency\IndexView::class,
			'dbtech_credits_currency',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 */
	public function actionPurchaseCompleted(ParameterBag $params): AbstractReply
	{
		/** @var Currency $currency */
		$this->assertCurrencyExists($params->currency_id);

		return $this->message(\XF::phrase('dbtech_credits_thanks_for_your_purchase'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionDonate(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'username' => InputFilterer::STRING,
			'amount' => InputFilterer::UNUM,
			'message' => InputFilterer::STRING,
		]);

		if ($visitor->getDbtechCreditsCurrency($currency) < $input['amount'])
		{
			return $this->error(\XF::phrase('dbtech_credits_currency_donate_x_max_y', [
				'attempted' => $currency->prefix . $currency->getFormattedValue($input['amount']) . $currency->suffix,
				'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix,
			]));
		}

		/** @var DonateHandler $donateEvent */
		$donateEvent = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('donate')
		;
		if (!$donateEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		$userRepo = \XF::app()->repository(UserRepository::class);
		$recipient = $userRepo->getUserByNameOrEmail($input['username']);

		if (!$recipient || $recipient->user_id == $visitor->user_id)
		{
			// Bad amount
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var EventFinder $eventFinder */
		$eventFinder = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'donate')
		;

		/** @var AbstractCollection<Event> $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		// No naughty words in the message, thanks
		$message = \XF::app()->stringFormatter()->censorText($input['message']);

		// First test remove credits from source
		$sourceEvents = $donateEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => (-1 * $input['amount']),
			'message' => $message,
			'source_user_id' => $recipient->user_id,
		], $visitor);

		// Then test apply credits to recipient
		$targetEvents = $donateEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => $input['amount'],
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		if (!\count($sourceEvents) || !\count($targetEvents))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_cannot_donate_to_x', [
				'name' => $recipient->username,
			]));
		}

		// Then properly remove credits from source
		$donateEvent->apply($recipient->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => (-1 * $input['amount']),
			'message' => $message,
			'source_user_id' => $recipient->user_id,
		], $visitor);

		// Then properly apply credits to recipient
		$donateEvent->apply($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => $input['amount'],
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_donation_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionAdjust(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'username' => InputFilterer::STRING,
			'amount' => InputFilterer::UNUM,
			'message' => InputFilterer::STRING,
			'negate' => InputFilterer::BOOLEAN,
		]);

		/** @var AdjustHandler $adjustEvent */
		$adjustEvent = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('adjust')
		;
		if (!$adjustEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		// Make sure this is set
		$currency->verifyAdjustEvent();

		$userRepo = \XF::app()->repository(UserRepository::class);
		$recipient = $userRepo->getUserByNameOrEmail($input['username']);

		if (!$recipient)
		{
			// Bad amount
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var EventFinder $eventFinder */
		$eventFinder = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'adjust')
		;

		/** @var AbstractCollection<Event> $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		// No naughty words in the message, thanks
		$message = \XF::app()->stringFormatter()->censorText($input['message']);

		// Shorthand
		$multiplier = $input['negate'] ? (-1 * $input['amount']) : $input['amount'];

		// First test add or remove credits
		$adjustEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => $multiplier,
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// Then properly add or remove credits
		$adjustEvent->apply($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => $multiplier,
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_adjust_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionRedeem(ParameterBag $params): AbstractReply
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'code' => InputFilterer::STRING,
		]);

		/** @var AdjustHandler $redeemEvent */
		$redeemEvent = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('redeem')
		;
		if (!$redeemEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		// Make sure this is set
		$currency->verifyAdjustEvent();

		/** @var EventFinder $eventFinder */
		$eventFinder = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'redeem')
		;

		/** @var AbstractCollection<Event> $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$message = \XF::phrase('dbtech_credits_currency_x_used_code_y', [
			'user' => $visitor->username,
			'code' => $input['code'],
		]);

		$pendingTransactions = $redeemEvent->testApply([
			'currency_id' => $currency->currency_id,
			'owner_id' => $visitor->user_id,
			'message' => $message,
			'code' => $input['code'],
		], $visitor);

		if (!count($pendingTransactions))
		{
			// Bad amount
			return $this->notFound(\XF::phrase('dbtech_credits_redemption_code_invalid'));
		}

		// Then properly add or remove credits
		$redeemEvent->apply($input['code'], [
			'currency_id' => $currency->currency_id,
			'owner_id' => $visitor->user_id,
			'message' => $message,
			'code' => $input['code'],
		], $visitor);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_redeem_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionTransfer(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'to_currency_id' => InputFilterer::UNSIGNED,
			'amount' => InputFilterer::UNUM,
		]);

		if ($visitor->getDbtechCreditsCurrency($currency) < $input['amount'])
		{
			return $this->error(\XF::phrase('dbtech_credits_cancel_price_transfer', [
				'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix,
				'currency' => $currency->title,
			]));
		}

		/** @var Currency $toCurrency */
		$toCurrency = $this->assertCurrencyExists($input['to_currency_id'], [], 'dbtech_credits_invalid_currency');

		/** @var TransferHandler $transferEvent */
		$transferEvent = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('transfer')
		;
		if (!$transferEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		/** @var EventFinder $eventFinder */
		$eventFinder = \XF::app()->finder(EventFinder::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'transfer')
		;

		/** @var AbstractCollection<Event> $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$transferCurrencies = [];
		foreach ($events AS $event)
		{
			if ($event->event_trigger_id == 'transfer')
			{
				foreach ($event->Transfers AS $inboundTransfer)
				{
					$transferCurrencies[$inboundTransfer->currency_id] = $inboundTransfer->Currency;
				}
			}
		}

		if (!isset($transferCurrencies[$toCurrency->currency_id]))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$message = \XF::phrase('dbtech_credits_transfer_x_from_y_to_z', [
			'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix,
			'currency' => $currency->title,
			'new_currency' => $toCurrency->title,
		]);

		// Test if we have enough to remove
		$transferEvent->testUndo([
			'currency_id' => $currency->currency_id,
			'multiplier' 	=> (-1 * $input['amount']),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// Then remove from our old currency
		$transferEvent->undo($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' 	=> (-1 * $input['amount']),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// Then apply the amount to the new currency
		$transferEvent->apply($visitor->user_id, [
			'currency_id' => $toCurrency->currency_id,
			'multiplier' 	=> ($input['amount'] * ($currency->value / $toCurrency->value)),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_transfer_successful'));
	}


	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ExceptionAlias
	 * @throws \Exception
	 */
	public function actionGiftPurchase(ParameterBag $params): AbstractReply
	{
		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'event_id' => InputFilterer::UNSIGNED,
		]);

		/** @var PurchaseHandler $purchaseEvent */
		$purchaseEvent = \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('purchase')
		;
		if (!$purchaseEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		/** @var EventFinder $eventFinder */
		$eventFinder = \XF::app()->finder(EventFinder::class)
			->where('event_id', $input['event_id'])
		;

		/** @var Event $event */
		$event = $eventFinder->fetchOne();

		if (!$event)
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$profileIds = $event->getSetting('payment_profile_ids');
		$paymentRepo = \XF::app()->repository(PaymentRepository::class);

		/** @var AbstractCollection<PaymentProfile> $profiles */
		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$profileThirdParties = [];
		foreach ($profiles AS $profileId => $profile)
		{
			$profileId = (int) $profileId;

			$profileUsed = \in_array($profileId, $profileIds);
			if (!$profile->active || !$profileUsed)
			{
				unset($profiles[$profileId]);
				continue;
			}

			if (\XF::$versionId >= 2021270)
			{
				$profileThirdParties = array_merge(
					$profileThirdParties,
					$profile->Provider->getCookieThirdParties()
				);
			}
		}

		if (\XF::$versionId >= 2021270)
		{
			$this->assertCookieConsent([], array_unique($profileThirdParties));
		}

		$viewParams = [
			'currency' => $currency,
			'event' => $event,
			'profiles' => $profiles,
		];

		return $this->view(
			View\Currency\GiftPurchaseView::class,
			'dbtech_credits_currency_gift',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws PrintableException
	 */
	public function actionBuyContent(ParameterBag $params): AbstractReply
	{
		$input = $this->filter([
			'content_type' => InputFilterer::STRING,
			'content_id' => InputFilterer::UNSIGNED,
			'content_hash' => InputFilterer::STRING,
		]);

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Charge $charge */
		$charge = \XF::app()->finder(ChargeFinder::class)
			->where('content_type', $input['content_type'])
			->where('content_id', $input['content_id'])
			->where('content_hash', $input['content_hash'])
			->fetchOne()
		;
		if (!$charge)
		{
			// Bad hash
			return $this->error(\XF::phrase('dbtech_credits_invalid_hash'));
		}

		if ($charge->Purchases->offsetExists($visitor->user_id))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_already_owned'));
		}

		if ($this->isPost())
		{
			$contentEvent = $charge->getHandler();
			$content = $charge->Content;

			$extraParams = [
				'node_id' => $charge->content_type == 'post' ? $content->Thread->node_id : 0,
				'multiplier' => (-1 * $charge->cost),
				'owner_id' => $content->offsetExists('user_id') ? $content->user_id : 0,
				'currency_id' => $charge->Currency->currency_id,
				'content_type' => $charge->content_type,
				'content_id' => $charge->content_id,
				'alwaysCheck' => true,
			];

			$pendingTransactions = $contentEvent->testUndo($extraParams, $visitor);

			if (!count($pendingTransactions))
			{
				return $this->error(\XF::phrase('dbtech_credits_content_purchase_events_invalid'));
			}

			// Charge the current user
			$contentEvent->undo($charge->content_id, $extraParams, $visitor);

			if (!empty($content->User))
			{
				// Add this
				$extraParams['multiplier'] = $charge->cost;
				$extraParams['source_user_id'] = $visitor->user_id;

				// Apply the event to the post owner, in case ownership settings are configured
				$contentEvent->apply($charge->content_id, $extraParams, $content->User);
			}

			try
			{
				$chargePurchase = \XF::app()->em()->create(ChargePurchase::class);
				$chargePurchase->content_type = $charge->content_type;
				$chargePurchase->content_id = $charge->content_id;
				$chargePurchase->content_hash = $charge->content_hash;
				$chargePurchase->user_id = $visitor->user_id;
				$chargePurchase->save();
			}
			/** @noinspection PhpRedundantCatchClauseInspection */
			catch (DuplicateKeyException $e)
			{
			}

			$redirect = $this->getDynamicRedirect();
			if ($content instanceof LinkableInterface)
			{
				// Redirect back to the content if we can
				$redirect = $content->getContentUrl();
			}

			// And we're done
			return $this->redirect($redirect, \XF::phrase('dbtech_credits_unlock_successful'));
		}

		$viewParams = [
			'currency' => $charge->Currency,
			'charge' => $charge,
		];

		// Output
		return $this->view(
			View\Currency\BuyContentView::class,
			'dbtech_credits_currency_unlock',
			$viewParams
		);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Currency
	 * @throws ExceptionAlias
	 */
	protected function assertCurrencyExists(?int $id, array $with = [], ?string $phraseKey = null): Currency
	{
		return $this->assertRecordExists(Currency::class, $id, $with, $phraseKey);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return User
	 * @throws ExceptionAlias
	 */
	protected function assertUserExists(?int $id, array $with = [], ?string $phraseKey = null): User
	{
		return $this->assertRecordExists(User::class, $id, $with, $phraseKey ?: 'requested_user_not_found');
	}
}