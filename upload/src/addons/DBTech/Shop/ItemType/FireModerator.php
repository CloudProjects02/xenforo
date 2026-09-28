<?php

namespace DBTech\Shop\ItemType;

/**
 * Class FireModerator
 *
 * @package DBTech\Shop\ItemType
 */
class FireModerator extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'unfire' => 1,
		'mods' => []
	];

	/** @var array */
	protected $defaultUserConfig = [
		'moderatorid' => 0,
		'userid' => 0,
		'forumid' => 0,
		'moderator_config' => []
	];
	
	
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
	
	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		// TODO: Add user config view / switch to switch statement
		$previous = parent::getDefaultTemplateParams($context);
		
		if ($context == 'admin_config')
		{
			/** @var \XF\Repository\Moderator $modRepo */
			$modRepo = \XF::repository('XF:Moderator');
			
			$handlers = $modRepo->getModeratorHandlers();
			$contentModerators = $modRepo->findContentModeratorsForList()->fetch();
			$contentModerators = $contentModerators->filter(
				function (\XF\Entity\ModeratorContent $moderatorContent) use ($handlers): bool
				{
					return isset($handlers[$moderatorContent->content_type]);
				}
			);
			
			$users = $contentModerators->pluckNamed('User', 'user_id');
			
			$previous = array_merge($previous, [
				'contentModerators' => $contentModerators->groupBy('user_id'),
				'users' => $users
			]);
		}
		
		return $previous;
	}
	
	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return $this->app()->inputFilterer()->filterArray($config, [
			'mods' => 'array-uint',
		]);
	}
	
	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterUserConfig(array $input = []): array
	{
		return $this->app()->inputFilterer()->filterArray($input, [
			'moderatorid' => 'uint',
		]);
	}
	
	/**
	 * @param array $configuration
	 * @param null $errors
	 *
	 * @return bool
	 */
	public function validateUserConfig(array &$configuration = [], &$errors = null): bool
	{
		if (!in_array($configuration['moderatorid'], $this->item->code['mods']))
		{
			$errors = \XF::phraseDeferred('dbtech_shop_you_may_not_fire_this_moderator');
			return false;
		}
		
		$contentModerator = $this->em()->find('XF:ModeratorContent', $configuration['moderatorid']);
		if (!$contentModerator)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_no_content_moderator_could_be_found_with_id_x', ['moderator_id' => $configuration['moderatorid']]);
			return false;
		}
		
		$configuration['userid'] = $contentModerator->user_id;
		$configuration['forumid'] = $contentModerator->content_id;
		$configuration['moderator_config'] = $contentModerator->toArray(false);
		
		// TODO: Save the moderator permissions somehow
		
		return true;
	}
	
	/**
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		$userConfig = $this->purchase->configuration;
		
		/** @var \XF\Entity\Forum $forum */
		$forum = $this->em()->find('XF:Forum', $userConfig['forumid'], ['Node']);
		if (!$forum)
		{
			return '';
		}
		
		/** @var \XF\Entity\User $user */
		$user = $this->em()->find('XF:User', $userConfig['userid']);
		if (!$user)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_firemoderator', [
			'user_url' => $this->app()->router('public')->buildLink('full:members', $user),
			'user' => new \XF\PreEscaped($user->username),
			
			'forum_url' => $this->app()->router('public')->buildLink('full:' . $forum->Node->getRoute(), $forum),
			'forum' => new \XF\PreEscaped($forum->title)
		]);
	}
}