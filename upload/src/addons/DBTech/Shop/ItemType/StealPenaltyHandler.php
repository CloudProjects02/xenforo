<?php

namespace DBTech\Shop\ItemType;

class StealPenaltyHandler extends AbstractHandler
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
		$this->addListener('steal_penalty', function (&$stealPenalty)
		{
			$stealPenalty = max(
				\XF::app()->options()->dbtech_shop_maxsteal_lose,
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
		return \XF::app()->inputFilterer()->filterArray($config, [
			'value' => 'uint',
		]);
	}
}