<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Cart;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\PurchaseRepository;
use DBTech\Shop\Service\Cart\CompleteService;
use DBTech\Shop\Service\Cart\CreatorService;
use DBTech\Shop\XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Repository\UserRepository;

class CheckoutController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		if (!$visitor->canPurchaseDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}


	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionIndex(): AbstractReply
	{
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/checkout'));

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Cart> $cartItems */
		$cartItems = $this->getCart();

		$totalPrices = $currencies = [];
		foreach ($cartItems AS $cartItem)
		{
			$currency = $cartItem->getCurrency();

			if (!isset($totalPrices[$currency->currency_id]))
			{
				$totalPrices[$currency->currency_id] = [
					'total' => 0.00,
					'currency' => $currency,
				];
			}

			$totalPrices[$currency->currency_id]['total'] += $cartItem->getPrice();
			$currencies[$currency->currency_id] = $currency;
		}

		$viewParams = [
			'cartItems' => $cartItems,
			'totalPrices' => $totalPrices,
			'currencies' => $currencies,
		];
		return $this->view(
			View\Checkout\IndexView::class,
			'dbtech_shop_checkout',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionRemoveItem(ParameterBag $params): AbstractReply
	{
		$cartItems = $this->getCart();
		$item = $this->assertItemExists($params->item_id);

		if ($this->isPost())
		{
			$cartItem = $this->getCartItemFromInput($params);
			$cartItem?->delete();

			return $this->redirect($this->getDynamicRedirect($this->buildLink('dbtech-shop/checkout')));
		}
		else
		{
			$viewParams = [
				'item' => $item,
			];
			return $this->view(
				View\Checkout\RemoveItemView::class,
				'dbtech_shop_checkout_remove_item',
				$viewParams
			);
		}
	}

	/**
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionUpdate(): AbstractReply
	{
		$this->assertPostOnly();

		$cartItems = $this->getCart();

		if ($this->filter('delete', 'bool'))
		{
			foreach ($this->filter('cart_keys', 'array') AS $key)
			{
				if ($cartItems->offsetExists($key))
				{
					/** @var Cart $cartItem */
					$cartItem = $cartItems->offsetGet($key);
					$cartItem->delete();

					$cartItems->offsetUnset($key);
				}
			}

			if (!$cartItems->count())
			{
				return $this->redirect($this->buildLink('dbtech-shop'), \XF::phrase('dbtech_shop_cart_updated'));
			}
		}
		else
		{
			foreach ($this->filter('quantity', 'array') AS $key => $quantity)
			{
				if ($cartItems->offsetExists($key))
				{
					/** @var Cart $cartItem */
					$cartItem = $cartItems->offsetGet($key);

					$creator = \XF::app()->service(CreatorService::class);
					$creator->setQuantity($cartItem, $quantity);

					if (!$creator->validate($errors))
					{
						return $this->error($errors);
					}

					$creator->save();
				}
			}

			if ($this->request->exists('purchase'))
			{
				return $this->rerouteController(__CLASS__, 'complete');
			}
		}

		return $this->redirect($this->buildLink('dbtech-shop/checkout'), \XF::phrase('dbtech_shop_cart_updated'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws PrintableException
	 */
	public function actionRecipient(ParameterBag $params): AbstractReply
	{
		/** @var Cart $cartItem */
		$cartItem = $this->getCartItemFromInput($params);
		if (!$cartItem)
		{
			return $this->error(\XF::phrase('dbtech_shop_this_item_is_not_in_cart'));
		}

		if (!$cartItem->Item->isGiftable())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_gift_item_x', [
				'item' => $cartItem->Item->title,
			]));
		}

		if ($this->isPost())
		{
			$toUser = null;
			if ($cartItem->Item->isGiftable())
			{
				if ($cartItem->Item->isOnlyGiftable() || $this->request->exists('is_gift'))
				{
					$toUser = \XF::app()->repository(UserRepository::class)
						->getUserByNameOrEmail($this->filter('recipient', 'str'))
					;
					if (!$toUser)
					{
						return $this->error(\XF::phrase('requested_user_not_found'));
					}
				}
			}

			$creator = \XF::app()->service(CreatorService::class);
			$creator->setGiftOptions($cartItem, $toUser, $this->filter('message', 'str'));

			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}

			$creator->save();

			return $this->redirect($this->buildLink('dbtech-shop/checkout'), \XF::phrase('dbtech_shop_gift_options_updated'));
		}

		$viewParams = [
			'cartItem' => $cartItem,
			'item' => $cartItem->Item,
		];
		return $this->view(
			View\Checkout\RecipientView::class,
			'dbtech_shop_checkout_recipient',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionComplete(): AbstractReply
	{
		$purchase = \XF::app()->service(CompleteService::class);

		if (!$purchase->validate($errors))
		{
			return $this->error($errors);
		}

		$purchase->save();

		return $this->redirect($this->buildLink('dbtech-shop/inventory'), \XF::phrase('dbtech_shop_purchase_completed'));
	}

	/**
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Cart>
	 */
	public function getCart(): AbstractCollection
	{
		return \XF::app()->repository(PurchaseRepository::class)
			->getCart()
			->fetch();
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return Cart|null
	 */
	public function getCartItemFromInput(ParameterBag $params): ?Cart
	{
		$cartItems = $this->getCart();

		// This is the $cartItems offset
		$key = \XF::visitor()->user_id .
			'-' . $params->item_id .
			'-' . $this->filter('recipient_user_id', 'uint', 0)
		;

		return $cartItems->offsetExists($key) ? $cartItems->offsetGet($key) : null;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Item
	 * @throws Exception
	 */
	protected function assertItemExists(?int $id, array $with = [], ?string $phraseKey = null): Item
	{
		return $this->assertRecordExists(Item::class, $id, $with, $phraseKey);
	}

	/**
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_cart');
	}
}