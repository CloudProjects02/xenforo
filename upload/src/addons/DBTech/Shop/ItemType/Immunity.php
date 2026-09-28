<?php

namespace DBTech\Shop\ItemType;

/**
 * Class Immunity
 *
 * @package DBTech\Shop\ItemType
 */
class Immunity extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'value' => [
			'usernamechange' => false,
			'usertitlechange' => false,
			'avatarchange' => false,
			'theft' => false,
			'threadban' => false
		]
	];
	
	
	/**
	 *
	 */
	public function addListeners()
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
		return $this->app()->inputFilterer()->filterArray($config, [
			'value' => 'array-bool',
		]);
	}
}