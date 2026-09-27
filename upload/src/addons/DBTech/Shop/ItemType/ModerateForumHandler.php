<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Forum;
use XF\Entity\Moderator;
use XF\Entity\ModeratorContent;
use XF\Finder\ModeratorContentFinder;
use XF\PrintableException;
use XF\Repository\ModeratorRepository;
use XF\Repository\NodeRepository;
use XF\Service\UpdatePermissionsService;

class ModerateForumHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
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
				$nodeRepo = \XF::app()->repository(NodeRepository::class);

				$choices = $nodeRepo->getNodeOptionsData(false, 'Forum', 'option');
				$params['choices'] = array_map(function (array $v): array
				{
					$v['label'] = \XF::escapeString($v['label']);
					return $v;
				}, $choices);

				$modRepo = \XF::app()->repository(ModeratorRepository::class);
				$moderatorPermissionData = $modRepo->getModeratorPermissionData('node');

				$params['interfaceGroups'] = $moderatorPermissionData['interfaceGroups'];
				$params['contentPermissions'] = $moderatorPermissionData['contentPermissions'];
				break;

			case 'user_config_view':
				$params['forum'] = \XF::app()->em()->find(Forum::class, $this->item->code['forumid'], ['Node']);

				$modRepo = \XF::app()->repository(ModeratorRepository::class);
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
		return \XF::app()->inputFilterer()->filterArray($config, [
			'forumid' => 'uint',
			'modperms' => 'array',
		]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function activateAlways(): void
	{
		$purchase = $this->purchase;
		$adminConfig = $this->item->code;

		$contentModerator = \XF::app()->finder(ModeratorContentFinder::class)
			->where([
				'content_type' => 'node',
				'content_id' => $adminConfig['forumid'],
				'user_id' => $purchase->user_id,
			])
			->fetchOne();
		if ($contentModerator)
		{
			return;
		}

		$generalModerator = \XF::app()->em()->find(Moderator::class, $purchase->user_id);
		if (!$generalModerator)
		{
			$generalModerator = \XF::app()->em()->create(Moderator::class);
			$generalModerator->user_id = $purchase->user_id;
			$generalModerator->save();
		}

		$contentModerator = \XF::app()->em()->create(ModeratorContent::class);
		$contentModerator->content_type = 'node';
		$contentModerator->content_id = $adminConfig['forumid'];
		$contentModerator->user_id = $purchase->user_id;
		$contentModerator->save();

		$permissionUpdater = \XF::app()->service(UpdatePermissionsService::class);
		$permissionUpdater->setUser($purchase->User);
		$permissionUpdater->setContent($contentModerator->content_type, $contentModerator->content_id);
		$permissionUpdater->updatePermissions($adminConfig['modperms']);
	}

	/**
	 * @param null $error
	 *
	 * @throws PrintableException
	 */
	protected function _deactivate(&$error = null): void
	{
		$purchase = $this->purchase;
		$adminConfig = $this->item->code;

		$contentModerator = \XF::app()->finder(ModeratorContentFinder::class)
			->where([
				'content_type' => 'node',
				'content_id' => $adminConfig['forumid'],
				'user_id' => $purchase->user_id,
			])
			->fetchOne();
		if (!$contentModerator)
		{
			return;
		}

		$contentModerator->delete();
	}
}