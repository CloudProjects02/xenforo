<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Repository\PurchaseRepository;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class Cart extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'excludedpages' => '',
	];

	/**
	 * @return WidgetRenderer
	 */
	public function render(): WidgetRenderer
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Cart> $shoppingCart */
		$shoppingCart = \XF::app()->repository(PurchaseRepository::class)
			->getCart()
			->fetch()
		;

		$totalPrices = $currencies = [];
		foreach ($shoppingCart AS $cartItem)
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
			'cartItems' => $shoppingCart,
			'totalPrices' => $totalPrices,
			'currencies' => $currencies,
		];
		return $this->renderer('dbtech_shop_widget_cart', $viewParams);
	}
}