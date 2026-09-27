<?php

namespace DBTech\Shop\ItemType;

use XF\PrintableException;

class UserTitleChangePreDefinedHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'usertitle' => '',
	];


	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($config, [
			'usertitle' => 'str',
		]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function activateAlways(): void
	{
		if ($this->performValidations)
		{
			if ($this->item->isOnlyGiftable() && !$this->purchase->gifted)
			{
				return;
			}
		}

		$user = $this->purchase->User;
		$user->setOption('admin_edit', true);
		$user->custom_title = $this->item->code['usertitle'];

		if ($user->preSave())
		{
			$user->save();
		}
		else
		{
			$user->reset();
		}
	}
}