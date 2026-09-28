<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\Currency;
use XF\Widget\AbstractWidget;

/**
 * Class Wallet
 *
 * @package DBTech\Shop\Widget
 */
class Wallet extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'currencyIds' => ''
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
			$params['currencies'] = $this->finder('DBTech\Shop:Currency')
				->fetch()
				->pluckNamed('title', 'currency_id')
			;
		}
		return $params;
	}
	
	/**
	 * @return \XF\Widget\WidgetRenderer
	 */
	public function render(): \XF\Widget\WidgetRenderer
	{
		$options = $this->options;
		
		/** @var \DBTech\Shop\Entity\Currency[] $currencies */
		$currencies = $this->finder('DBTech\Shop:Currency')
			->fetch()
			->filterViewable()
			->filter(function (Currency $currency) use ($options): ?Currency
			{
				if (
					$options['currencyIds']
					&& !in_array(0, $options['currencyIds'])
					&& !in_array($currency->currency_id, $options['currencyIds'])
				) {
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
	 * @param \XF\Http\Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'currencyIds' => 'array-uint'
		]);
		if (empty($options['currencyIds']))
		{
			$error = \XF::phrase('dbtech_shop_wallet_widget_must_be_configured_to_display_one_or_more_currency');
			return false;
		}
		return true;
	}
}