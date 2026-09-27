<?php

namespace DBTech\Shop\ItemType;

class StealChanceHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'value' => 0,
	];


	/**
	 *
	 */
	public function addListeners(): void
	{
		$this->addListener('steal_chance', function (&$stealChance)
		{
			$stealChance = min(
				\XF::app()->options()->dbtech_shop_maxsteal_chance,
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
		return \XF::app()->inputFilterer()->filterArray($config, [
			'value' => 'uint',
		]);
	}
}