<?php

namespace DBTech\Shop\Widget;

use XF\Mvc\Entity\ArrayCollection;
use XF\Widget\AbstractWidget;

/**
 * Class Cart
 *
 * @package DBTech\Shop\Widget
 */
class Cart extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'excludedpages' => ''
	];
	
	/**
	 * @return \XF\Widget\WidgetRenderer
	 */
	public function render(): \XF\Widget\WidgetRenderer
	{
		/** @var \DBTech\Shop\Entity\Cart[]|ArrayCollection $shoppingCart */
		$shoppingCart = $this->repository('DBTech\Shop:Purchase')
			->getCart()
			->fetch()
		;
		
		$totalPrices = $currencies = [];
		foreach ($shoppingCart as $cartItem)
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
			'cartItems' => $shoppingCart,
			'totalPrices' => $totalPrices,
			'currencies' => $currencies
		];
		return $this->renderer('dbtech_shop_widget_cart', $viewParams);
	}
}