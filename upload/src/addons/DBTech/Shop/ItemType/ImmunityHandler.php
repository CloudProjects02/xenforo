<?php

namespace DBTech\Shop\ItemType;

class ImmunityHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'value' => [
			'usernamechange' => false,
			'usertitlechange' => false,
			'avatarchange' => false,
			'theft' => false,
			'threadban' => false,
		],
	];


	/**
	 *
	 */
	public function addListeners(): void
	{
		$this->addListener('immunity', function ($action, &$retval)
		{
			if (!isset($this->item->code['value'][$action]))
			{
				return;
			}

			$retval = ($retval || $this->item->code['value'][$action]);
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
			'value' => 'array-bool',
		]);
	}
}