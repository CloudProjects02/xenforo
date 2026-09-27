<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Finder\CurrencyFinder;
use XF\Http\Request;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class Richest extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'limit' => 10,
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
			'title' => $this->getTitle() ?: \XF::phrase('dbtech_shop_richest_users'),
			'currencies' => $currencies,
		];
		return $this->renderer('dbtech_shop_widget_richest', $viewParams);
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
			'limit' => 'uint',
			'currencyIds' => 'array-uint',
		]);
		if (empty($options['limit']))
		{
			$error = \XF::phrase('dbtech_shop_richest_widget_must_be_configured_to_display_one_or_more_user');
			return false;
		}
		if (empty($options['currencyIds']))
		{
			$error = \XF::phrase('dbtech_shop_richest_widget_must_be_configured_to_display_one_or_more_currency');
			return false;
		}
		return true;
	}
}