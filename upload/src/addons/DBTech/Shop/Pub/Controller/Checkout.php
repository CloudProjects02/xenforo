<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Checkout
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Checkout extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
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
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/checkout'));
		
		/** @var \DBTech\Shop\Entity\Cart[]|ArrayCollection $cartItems */
		$cartItems = $this->getCart();
		
		$totalPrices = $currencies = [];
		foreach ($cartItems as $cartItem)
		{
			$currency = $cartItem->getCurrency();
			
			if (!isset($totalPrices[$currency->currency_id]))
			{
				$totalPrices[$currency->currency_id] = [
					'total' => 0.00,
					'currency' => $currency
				];
			}
			
			$totalPrices[$currency->currency_id]['total'] += $cartItem->getPrice();
			$currencies[$currency->currency_id] = $currency;
		}
		
		$viewParams = [
			'cartItems' => $cartItems,
			'totalPrices' => $totalPrices,
			'currencies' => $currencies
		];
		return $this->view('DBTech\Shop:Checkout\Index', 'dbtech_shop_checkout', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionRemoveItem(ParameterBag $params)
	{
		$cartItems = $this->getCart();
		$item = $this->assertItemExists($params->item_id);
		
		if ($this->isPost())
		{
			$cartItem = $this->getCartItemFromInput($params);
			if ($cartItem)
			{
				$cartItem->delete();
			}
			
			return $this->redirect($this->getDynamicRedirect($this->buildLink('dbtech-shop/checkout')));
		}
		else
		{
			$viewParams = [
				'item' => $item,
			];
			return $this->view('DBTech\Shop:Checkout\RemoveItem', 'dbtech_shop_checkout_remove_item', $viewParams);
		}
	}
	
	/**
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionUpdate()
	{
		$this->assertPostOnly();
		
		$cartItems = $this->getCart();
		
		if ($this->filter('delete', 'bool'))
		{
			foreach ($this->filter('cart_keys', 'array') as $key)
			{
				if ($cartItems->offsetExists($key))
				{
					/** @var \DBTech\Shop\Entity\Cart $cartItem */
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
			foreach ($this->filter('quantity', 'array') as $key => $quantity)
			{
				if ($cartItems->offsetExists($key))
				{
					/** @var \DBTech\Shop\Entity\Cart $cartItem */
					$cartItem = $cartItems->offsetGet($key);
					
					/** @var \DBTech\Shop\Service\Cart\Creator $creator */
					$creator = $this->service('DBTech\Shop:Cart\Creator');
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
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\PrintableException
	 */
	public function actionRecipient(ParameterBag $params)
	{
		/** @var \DBTech\Shop\Entity\Cart $cartItem */
		$cartItem = $this->getCartItemFromInput($params);
		if (!$cartItem)
		{
			return $this->error(\XF::phrase('dbtech_shop_this_item_is_not_in_cart'));
		}

		if (!$cartItem->Item->isGiftable())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_gift_item_x', [
				'item' => $cartItem->Item->title
			]));
		}

		if ($this->isPost())
		{
			$toUser = null;
			if ($cartItem->Item->isGiftable())
			{
				if ($cartItem->Item->isOnlyGiftable() || $this->request->exists('is_gift'))
				{
					/** @var \XF\Entity\User $toUser */
					$toUser = $this->repository('XF:User')
						->getUserByNameOrEmail($this->filter('recipient', 'str'))
					;
					if (!$toUser)
					{
						return $this->error(\XF::phrase('requested_user_not_found'));
					}
				}
			}
			
			/** @var \DBTech\Shop\Service\Cart\Creator $creator */
			$creator = $this->service('DBTech\Shop:Cart\Creator');
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
			'item' => $cartItem->Item
		];
		return $this->view('DBTech\Shop:Checkout\Recipient', 'dbtech_shop_checkout_recipient', $viewParams);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
	 */
	public function actionComplete()
	{
		/** @var \DBTech\Shop\Service\Cart\Complete $purchase */
		$purchase = \XF::app()->service('DBTech\Shop:Cart\Complete');
		
		if (!$purchase->validate($errors))
		{
			return $this->error($errors);
		}
		
		$purchase->save();
		
		return $this->redirect($this->buildLink('dbtech-shop/inventory'), \XF::phrase('dbtech_shop_purchase_completed'));
	}

	/**
	 * @return \DBTech\Shop\Entity\Cart[]|\XF\Mvc\Entity\AbstractCollection
	 */
	public function getCart()
	{
		return $this->repository('DBTech\Shop:Purchase')
			->getCart()
			->fetch();
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \DBTech\Shop\Entity\Cart|null
	 */
	public function getCartItemFromInput(ParameterBag $params): ?\DBTech\Shop\Entity\Cart
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
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Item|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertItemExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Item', $id, $with, $phraseKey);
	}

	/**
	 * @param array $activities
	 *
	 * @return bool|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('dbtech_shop_viewing_cart');
	}
}