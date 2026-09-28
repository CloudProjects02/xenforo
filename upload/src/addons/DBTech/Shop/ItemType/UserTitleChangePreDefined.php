<?php

namespace DBTech\Shop\ItemType;

/**
 * Class UserTitleChangePreDefined
 *
 * @package DBTech\Shop\ItemType
 */
class UserTitleChangePreDefined extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'usertitle' => '',
	];
	
	
	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return $this->app()->inputFilterer()->filterArray($config, [
			'usertitle' => 'str',
		]);
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function activateAlways()
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