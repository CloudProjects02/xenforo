<?php

namespace DBTech\Shop\ItemType;

/**
 * Class StealPenalty
 *
 * @package DBTech\Shop\ItemType
 */
class StealPenalty extends AbstractHandler
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
		$this->addListener('steal_penalty', function (&$stealPenalty)
		{
			$stealPenalty = max(
				$this->options()->dbtech_shop_maxsteal_lose,
				($this->item->code['value'] + $stealPenalty)
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