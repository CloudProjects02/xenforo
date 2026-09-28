<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ModerateForum
 *
 * @package DBTech\Shop\ItemType
 */
class ModerateForum extends AbstractHandler
{
	/** @var array */
	protected $defaultAdminConfig = [
		'forumid' => '',
		'modperms' => [],
	];
	
	
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
				
				/** @var \XF\Repository\Moderator $modRepo */
				$modRepo = $this->repository('XF:Moderator');
				$moderatorPermissionData = $modRepo->getModeratorPermissionData('node');
				
				$params['interfaceGroups'] = $moderatorPermissionData['interfaceGroups'];
				$params['contentPermissions'] = $moderatorPermissionData['contentPermissions'];
				break;
			
			case 'user_config_view':
				$params['forum'] = $this->em()->find('XF:Forum', $this->item->code['forumid'], ['Node']);
				
				/** @var \XF\Repository\Moderator $modRepo */
				$modRepo = $this->repository('XF:Moderator');
				$moderatorPermissionData = $modRepo->getModeratorPermissionData('node');
				
				$params['interfaceGroups'] = $moderatorPermissionData['interfaceGroups'];
				$params['contentPermissions'] = $moderatorPermissionData['contentPermissions'];
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
			'forumid' => 'uint',
			'modperms' => 'array',
		]);
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function activateAlways()
	{
		$purchase = $this->purchase;
		$adminConfig = $this->item->code;
		
		$contentModerator = $this->finder('XF:ModeratorContent')
			->where([
				'content_type' => 'node',
				'content_id' => $adminConfig['forumid'],
				'user_id' => $purchase->user_id
			])
			->fetchOne();
		if ($contentModerator)
		{
			return;
		}
		
		$generalModerator = $this->em()->find('XF:Moderator', $purchase->user_id);
		if (!$generalModerator)
		{
			/** @var \XF\Entity\Moderator $generalModerator */
			$generalModerator = $this->em()->create('XF:Moderator');
			$generalModerator->user_id = $purchase->user_id;
			$generalModerator->save();
		}
		
		/** @var \XF\Entity\ModeratorContent $contentModerator */
		$contentModerator = $this->em()->create('XF:ModeratorContent');
		$contentModerator->content_type = 'node';
		$contentModerator->content_id = $adminConfig['forumid'];
		$contentModerator->user_id = $purchase->user_id;
		$contentModerator->save();
		
		/** @var \XF\Service\UpdatePermissions $permissionUpdater */
		$permissionUpdater = $this->service('XF:UpdatePermissions');
		$permissionUpdater->setUser($purchase->User);
		$permissionUpdater->setContent($contentModerator->content_type, $contentModerator->content_id);
		$permissionUpdater->updatePermissions($adminConfig['modperms']);
	}
	
	/**
	 * @param null $error
	 *
	 * @throws \XF\PrintableException
	 */
	protected function _deactivate(&$error = null)
	{
		$purchase = $this->purchase;
		$adminConfig = $this->item->code;
		
		$contentModerator = $this->finder('XF:ModeratorContent')
			->where([
				'content_type' => 'node',
				'content_id' => $adminConfig['forumid'],
				'user_id' => $purchase->user_id
			])
			->fetchOne();
		if (!$contentModerator)
		{
			return;
		}
		
		$contentModerator->delete();
	}
}