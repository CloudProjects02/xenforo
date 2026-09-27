<?php

namespace DBTech\Shop\ItemType;

use DBTech\Shop\Entity\ThreadBan;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\UserGroupRepository;

class ThreadBanHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'excludedforums' => [],
		'excludedgroups' => [],
		'onlyown' => true,
	];

	/** @var array */
	protected array $defaultUserConfig = [
		'threadid' => 0,
		'userid' => 0,
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

				$groupRepo = \XF::app()->repository(UserGroupRepository::class);
				$params['userGroups'] = $groupRepo->getUserGroupTitlePairs();
				break;

			case 'user_config':
				if ($this->purchase->configuration['userid'])
				{
					$params['user'] = \XF::app()->em()->find(User::class, $this->purchase->configuration['userid']);
				}
				break;

			case 'user_config_view':
				$params['thread'] = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
				$params['user'] = \XF::app()->em()->find(User::class, $this->purchase->configuration['userid']);
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
			'excludedforums' => 'array-uint',
			'excludedgroups' => 'array-uint',
			'onlyown' => 'bool',
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
			'threadid' => 'str',
			'username' => 'str',
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
		if (empty($configuration['threadid']) || empty($configuration['username']))
		{
			$errors = \XF::phraseDeferred('please_complete_required_fields');
			return false;
		}

		if (is_numeric($configuration['threadid']))
		{
			$thread = \XF::app()->em()->find(Thread::class, $configuration['threadid']);
			if (!$thread)
			{
				$errors = \XF::phraseDeferred('no_thread_could_be_found_with_id_x', ['thread_id' => $configuration['threadid']]);
				return false;
			}
		}
		else
		{
			$threadRepo = \XF::app()->repository(ThreadRepository::class);
			$thread = $threadRepo->getThreadFromUrl($configuration['threadid'], null, $errors);
			if (!$thread)
			{
				return false;
			}

			$configuration['threadid'] = $thread->thread_id;
		}

		if ($this->item->code['onlyown']
			&& $thread->user_id != $this->purchase->user_id)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_you_can_only_ban_from_your_own_threads');
			return false;
		}

		$user = \XF::app()->finder(UserFinder::class)
			->where('username', $configuration['username'])
			->fetchOne()
		;
		if (!$user)
		{
			$errors = \XF::phraseDeferred('requested_user_x_not_found', ['name' => $configuration['username']]);
			return false;
		}

		$configuration['userid'] = $user->user_id;
		unset($configuration['username']);

		if ($configuration['threadid'] != $this->purchase->configuration['threadid']
			|| $configuration['userid'] != $this->purchase->configuration['userid']
		)
		{
			$threadBan = \XF::app()->em()->find(ThreadBan::class, [
				'thread_id' => $thread->thread_id,
				'user_id' => $user->user_id,
			]);
			if ($threadBan)
			{
				$errors = \XF::phraseDeferred('dbtech_shop_user_already_banned_from_this_thread');
				return false;
			}
		}

		$retval = false;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->filterActivePurchasesForUser($user)
		;
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('immunity', ['threadban', &$retval]);
		}

		if ($retval)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_user_immune_to_threadban');
			return false;
		}

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		$thread = \XF::app()->em()->find(Thread::class, $userConfig['threadid']);
		if (!$thread)
		{
			return '';
		}

		$user = \XF::app()->em()->find(User::class, $userConfig['userid']);
		if (!$user)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_threadban', [
			'user_url' => \XF::app()->router('public')->buildLink('full:members', $user),
			'user' => new PreEscaped($user->username),

			'thread_url' => \XF::app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new PreEscaped($thread->title),
		]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function activateAlways(): void
	{
		$userConfig = $this->purchase->configuration;

		if (!$userConfig['threadid'] || !$userConfig['userid'])
		{
			return;
		}

		$threadBan = \XF::app()->em()->create(ThreadBan::class);
		$threadBan->thread_id = $userConfig['threadid'];
		$threadBan->user_id = $userConfig['userid'];

		if ($threadBan->preSave())
		{
			$threadBan->save();
		}
	}

	/**
	 * @param null $error
	 *
	 * @throws PrintableException
	 */
	protected function _deactivate(&$error = null): void
	{
		$userConfig = $this->purchase->configuration;

		if (!$userConfig['threadid'] || !$userConfig['userid'])
		{
			return;
		}

		$threadBan = \XF::app()->em()->find(ThreadBan::class, [
			'thread_id' => $userConfig['threadid'],
			'user_id' => $userConfig['userid'],
		]);
		if ($threadBan)
		{
			$threadBan->delete();
		}
	}
}