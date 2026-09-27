<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Finder\CurrencyFinder;
use XF\Http\Request;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class Wallet extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'currencyIds' => '',
	];

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams($context): array
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$params['currencies'] = \XF::app()->finder(CurrencyFinder::class)
				->fetch()
				->pluckNamed('title', 'currency_id')
			;
		}
		return $params;
	}

	/**
	 * @return WidgetRenderer
	 */
	public function render(): WidgetRenderer
	{
		$options = $this->options;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $currencies */
		$currencies = \XF::app()->finder(CurrencyFinder::class)
			->fetch()
			->filterViewable()
			->filter(function (Currency $currency) use ($options): ?Currency
			{
				if (
					$options['currencyIds']
					&& !in_array(0, $options['currencyIds'])
					&& !in_array($currency->currency_id, $options['currencyIds'])
				)
				{
					return null;
				}

				return $currency;
			})
		;

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('dbtech_shop_your_wallet'),
			'currencies' => $currencies,
		];
		return $this->renderer('dbtech_shop_widget_wallet', $viewParams);
	}

	/**
	 * @param Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'currencyIds' => 'array-uint',
		]);
		if (empty($options['currencyIds']))
		{
			$error = \XF::phrase('dbtech_shop_wallet_widget_must_be_configured_to_display_one_or_more_currency');
			return false;
		}
		return true;
	}
}