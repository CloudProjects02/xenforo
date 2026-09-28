<?php

namespace DBTech\Shop\ItemType;

/**
 * Class StealMore
 *
 * @package DBTech\Shop\ItemType
 */
class StealMore extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'value' => 0,
	];
	
	
	/**
	 *
	 */
	public function addListeners()
	{
		$this->addListener('steal_amount', function (&$stealAmount)
		{
			$stealAmount = min(
				$this->options()->dbtech_shop_maxsteal_amount,
				($this->item->code['value'] + $stealAmount)
			);
		});
	}
	
	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return $this->app()->inputFilterer()->filterArray($config, [
			'value' => 'uint',
		]);
	}
}