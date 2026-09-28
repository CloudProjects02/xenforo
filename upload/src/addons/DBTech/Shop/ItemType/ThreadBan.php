<?php

namespace DBTech\Shop\ItemType;

use XF\Mvc\Entity\ArrayCollection;

/**
 * Class ThreadBan
 *
 * @package DBTech\Shop\ItemType
 */
class ThreadBan extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'excludedforums' => [],
		'excludedgroups' => [],
		'onlyown' => true
	];

	/** @var array */
	protected $defaultUserConfig = [
		'threadid' => 0,
		'userid' => 0
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
				
				/** @var \XF\Repository\UserGroup $groupRepo */
				$groupRepo = $this->repository('XF:UserGroup');
				$params['userGroups'] = $groupRepo->getUserGroupTitlePairs();
				break;
				
			case 'user_config':
				if ($this->purchase->configuration['userid'])
				{
					$params['user'] = $this->em()->find('XF:User', $this->purchase->configuration['userid']);
				}
				break;
			
			case 'user_config_view':
				$params['thread'] = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
				$params['user'] = $this->em()->find('XF:User', $this->purchase->configuration['userid']);
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
		return $this->app()->inputFilterer()->filterArray($input, [
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
			$thread = $this->em()->find('XF:Thread', $configuration['threadid']);
			if (!$thread)
			{
				$errors = \XF::phraseDeferred('no_thread_could_be_found_with_id_x', ['thread_id' => $configuration['threadid']]);
				return false;
			}
		}
		else
		{
			$threadRepo = $this->app()->repository('XF:Thread');
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
		
		/** @var \XF\Entity\User $user */
		$user = $this->finder('XF:User')->where('username', $configuration['username'])->fetchOne();
		if (!$user)
		{
			$errors = \XF::phraseDeferred('requested_user_x_not_found', ['name' => $configuration['username']]);
			return false;
		}
		
		$configuration['userid'] = $user->user_id;
		unset($configuration['username']);
		
		if ($configuration['threadid'] != $this->purchase->configuration['threadid']
			|| $configuration['userid'] != $this->purchase->configuration['userid']
		) {
			$threadBan = $this->em()->find('DBTech\Shop:ThreadBan', [
				'thread_id' => $thread->thread_id,
				'user_id' => $user->user_id
			]);
			if ($threadBan)
			{
				$errors = \XF::phraseDeferred('dbtech_shop_user_already_banned_from_this_thread');
				return false;
			}
		}

		$retval = false;

		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $this->repository('DBTech\Shop:Purchase')
			->filterActivePurchasesForUser($user)
		;
		foreach ($purchases as $purchase)
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
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		$userConfig = $this->purchase->configuration;
		
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $userConfig['threadid']);
		if (!$thread)
		{
			return '';
		}
		
		/** @var \XF\Entity\User $user */
		$user = $this->em()->find('XF:User', $userConfig['userid']);
		if (!$user)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_threadban', [
			'user_url' => $this->app()->router('public')->buildLink('full:members', $user),
			'user' => new \XF\PreEscaped($user->username),
			
			'thread_url' => $this->app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new \XF\PreEscaped($thread->title)
		]);
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function activateAlways()
	{
		$userConfig = $this->purchase->configuration;
		
		if (!$userConfig['threadid'] || !$userConfig['userid'])
		{
			return;
		}
		
		/** @var \DBTech\Shop\Entity\ThreadBan $threadBan */
		$threadBan = $this->em()->create('DBTech\Shop:ThreadBan');
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
	 * @throws \XF\PrintableException
	 */
	protected function _deactivate(&$error = null)
	{
		$userConfig = $this->purchase->configuration;
		
		if (!$userConfig['threadid'] || !$userConfig['userid'])
		{
			return;
		}
		
		$threadBan = $this->em()->find('DBTech\Shop:ThreadBan', [
			'thread_id' => $userConfig['threadid'],
			'user_id' => $userConfig['userid']
		]);
		if ($threadBan)
		{
			$threadBan->delete();
		}
	}
}