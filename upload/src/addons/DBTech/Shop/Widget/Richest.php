<?php

namespace DBTech\Shop\Widget;

use XF\Widget\AbstractWidget;

/**
 * Class Richest
 *
 * @package DBTech\Shop\Widget
 */
class Richest extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'limit' => 10,
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
			->filter(function (\DBTech\Shop\Entity\Currency $currency) use ($options): ?\DBTech\Shop\Entity\Currency
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
			'title' => $this->getTitle() ?: \XF::phrase('dbtech_shop_richest_users'),
			'currencies' => $currencies,
		];
		return $this->renderer('dbtech_shop_widget_richest', $viewParams);
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
			'limit' => 'uint',
			'currencyIds' => 'array-uint'
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