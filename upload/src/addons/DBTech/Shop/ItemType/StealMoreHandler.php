<?php

namespace DBTech\Shop\ItemType;

class StealMoreHandler extends AbstractHandler
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
		$this->addListener('steal_amount', function (&$stealAmount)
		{
			$stealAmount = min(
				\XF::app()->options()->dbtech_shop_maxsteal_amount,
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
		return \XF::app()->inputFilterer()->filterArray($config, [
			'value' => 'uint',
		]);
	}
}