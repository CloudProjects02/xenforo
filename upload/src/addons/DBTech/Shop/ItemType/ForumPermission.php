<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ForumPermission
 *
 * @package DBTech\Shop\ItemType
 */
class ForumPermission extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'value' => 0,
		'permissions' => []
	];
	
	
	/**
	 *
	 */
	public function addListeners()
	{
		if ($this->purchase->isActive())
		{
			$this->addListener('has_node_permission', function ($contentId, $permission, &$retval)
			{
				if (!empty($this->item->code['permissions']))
				{
					if (!isset($this->item->code['permissions'][$permission]))
					{
						return;
					}
					
					switch ($this->item->code['permissions'][$permission])
					{
						case 'unset':
							// nothing
							break;
							
						case 'content_allow':
						case 'allow':
							$retval = true;
							break;
							
						case 'reset':
						case 'deny':
							$retval = false;
							break;
					}
				}
				elseif ($permission == 'view')
				{
					$retval = true;
				}
			}, $this->item->code['value']);
		}
	}
	
	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		$params = parent::getDefaultTemplateParams($context);
		
		switch ($context)
		{
			case 'admin_config':
				/** @var \XF\Repository\Node $nodeRepo */
				$nodeRepo = $this->repository('XF:Node');
				
				$choices = $nodeRepo->getNodeOptionsData(false, 'Forum', 'option');
				$params['choices'] = array_map(function (array $v): array
				{
					$v['label'] = \XF::escapeString($v['label']);
					return $v;
				}, $choices);
				break;
			
			case 'user_config_view':
				$params['forum'] = $this->em()->find('XF:Forum', $this->item->code['value'], ['Node']);
				break;
		}
		
		return $params;
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