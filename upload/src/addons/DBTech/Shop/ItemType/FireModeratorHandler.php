<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Forum;
use XF\Entity\ModeratorContent;
use XF\Entity\User;
use XF\PreEscaped;
use XF\Repository\ModeratorRepository;

class FireModeratorHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'unfire' => 1,
		'mods' => [],
	];

	/** @var array */
	protected array $defaultUserConfig = [
		'moderatorid' => 0,
		'userid' => 0,
		'forumid' => 0,
		'moderator_config' => [],
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
			$modRepo = \XF::app()->repository(ModeratorRepository::class);

			$handlers = $modRepo->getModeratorHandlers();
			$contentModerators = $modRepo->findContentModeratorsForList()->fetch();
			$contentModerators = $contentModerators->filter(
				function (ModeratorContent $moderatorContent) use ($handlers): bool
				{
					return isset($handlers[$moderatorContent->content_type]);
				}
			);

			$users = $contentModerators->pluckNamed('User', 'user_id');

			$previous = array_merge($previous, [
				'contentModerators' => $contentModerators->groupBy('user_id'),
				'users' => $users,
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
		return \XF::app()->inputFilterer()->filterArray($config, [
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
		return \XF::app()->inputFilterer()->filterArray($input, [
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

		$contentModerator = \XF::app()->em()->find(ModeratorContent::class, $configuration['moderatorid']);
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
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		$forum = \XF::app()->em()->find(Forum::class, $userConfig['forumid'], ['Node']);
		if (!$forum)
		{
			return '';
		}

		$user = \XF::app()->em()->find(User::class, $userConfig['userid']);
		if (!$user)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_firemoderator', [
			'user_url' => \XF::app()->router('public')->buildLink('full:members', $user),
			'user' => new PreEscaped($user->username),

			'forum_url' => \XF::app()->router('public')->buildLink('full:' . $forum->Node->getRoute(), $forum),
			'forum' => new PreEscaped($forum->title),
		]);
	}
}