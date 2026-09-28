<?php

namespace DBTech\Shop\ItemType;

/**
 * Class StealChance
 *
 * @package DBTech\Shop\ItemType
 */
class StealChance extends AbstractHandler
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
		$this->addListener('steal_chance', function (&$stealChance)
		{
			$stealChance = min(
				$this->options()->dbtech_shop_maxsteal_chance,
				($this->item->code['value'] + $stealChance)
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