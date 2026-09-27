<?php

namespace DBTech\Credits\Payment;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Finder\CurrencyFinder;
use DBTech\Credits\Pub\View\PaymentView;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Reply\AbstractReply;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class Credits extends AbstractProvider
{
	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return 'DragonByte Credits';
	}

	/**
	 * @param PaymentProfile $profile
	 *
	 * @return string
	 */
	public function renderConfig(PaymentProfile $profile): string
	{
		$data = [
			'profile' => $profile,
			'currencies' => \XF::app()->finder(CurrencyFinder::class)
				->fetch()
				->pluckNamed('title', 'currency_id'),
		];
		return \XF::app()->templater()->renderTemplate('admin:payment_profile_' . $this->providerId, $data);
	}

	/**
	 * @param array $options
	 * @param array $errors
	 *
	 * @return bool
	 */
	public function verifyConfig(array &$options, &$errors = []): bool
	{
		$currency = \XF::app()->em()->find(Currency::class, $options['currency_id']);
		if (!$currency)
		{
			$errors[] = \XF::phrase('dbtech_credits_invalid_currency');
			return false;
		}

		if (empty($options['exchange_rate']))
		{
			$errors[] = \XF::phrase('dbtech_credits_must_specify_exchange_rate');
			return false;
		}

		return true;
	}

	/**
	 * @param Controller $controller
	 * @param PurchaseRequest $purchaseRequest
	 * @param Purchase $purchase
	 *
	 * @return AbstractReply
	 */
	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): AbstractReply
	{
		$paymentProfile = $purchase->paymentProfile;
		$cost = $purchase->cost * $paymentProfile->options['exchange_rate'];
		$currency = $this->getCurrencyFromPaymentProfile($paymentProfile);

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		try
		{
			$eventTriggerRepo->getHandler('payment')
				->testApply([
					'multiplier'      => $cost,
					'currency_id'     => $currency->currency_id,
					'purchaseRequest' => $purchaseRequest,
					'paymentProfile'  => $paymentProfile,
					'purchaser'       => $purchase->purchaser,
					'purchase'        => $purchase,
				], $purchase->purchaser)
			;
		}
		catch (\Exception $e)
		{
			return $controller->error($e->getMessage());
		}

		$viewParams = [
			'purchaseRequest' => $purchaseRequest,
			'paymentProfile'  => $paymentProfile,
			'purchaser'       => $purchase->purchaser,
			'purchase'        => $purchase,
			'currency'        => $currency,
			'cost'            => $cost,
		];

		return $controller->view(
			PaymentView::class,
			'dbtech_credits_payment_initiate',
			$viewParams
		);
	}

	/**
	 * @param Controller $controller
	 * @param PurchaseRequest $purchaseRequest
	 * @param PaymentProfile $paymentProfile
	 * @param Purchase $purchase
	 *
	 * @return AbstractReply
	 */
	public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase): AbstractReply
	{
		$refId = $purchaseRequest->purchase_request_id . '_' . md5(\XF::$time);
		$currency = $this->getCurrencyFromPaymentProfile($paymentProfile);

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		try
		{
			$eventTriggerRepo->getHandler('payment')
				->apply($refId, [
					'multiplier'      => $purchase->cost * $paymentProfile->options['exchange_rate'],
					'currency_id'     => $currency->currency_id,
					'purchaseRequest' => $purchaseRequest,
					'paymentProfile'  => $paymentProfile,
					'purchaser'       => $purchase->purchaser,
					'purchase'        => $purchase,
					'content_id'      => $purchaseRequest->purchase_request_id,
					'content_type'    => 'payment',
				], $purchase->purchaser)
			;
		}
		catch (\Exception $e)
		{
			return $controller->error($e->getMessage());
		}

		$state = new CallbackState();
		$state->transactionId = $refId;
		$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
		$state->purchaseRequest = $purchaseRequest;
		$state->paymentProfile = $paymentProfile;

		$this->completeTransaction($state);

		$this->log($state);

		return $controller->redirect($purchase->returnUrl, '');
	}

	/**
	 * @param Request $request
	 *
	 * @return CallbackState
	 */
	public function setupCallback(Request $request): CallbackState
	{
		return new CallbackState();
	}

	/**
	 * @param CallbackState $state
	 */
	public function getPaymentResult(CallbackState $state): void
	{
		$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
	}

	/**
	 * @param CallbackState $state
	 */
	public function prepareLogData(CallbackState $state): void
	{
		$state->logDetails = [];
	}

	/**
	 * @param PaymentProfile $paymentProfile
	 * @param null $error
	 *
	 * @return Currency|null
	 */
	protected function getCurrencyFromPaymentProfile(PaymentProfile $paymentProfile, &$error = null): ?Currency
	{
		if (empty($paymentProfile->options['currency_id']))
		{
			$error = \XF::phrase('dbtech_credits_invalid_currency');
			return null;
		}

		$currency = \XF::app()->em()->find(Currency::class, $paymentProfile->options['currency_id']);
		if (!$currency)
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return null;
		}

		return $currency;
	}
}