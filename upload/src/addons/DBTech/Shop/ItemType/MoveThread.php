<?php

namespace DBTech\Shop\ItemType;

/**
 * Class MoveThread
 *
 * @package DBTech\Shop\ItemType
 */
class MoveThread extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'excludedforums' => [],
		'onlyown' => false,
	];

	/** @var array */
	protected $defaultUserConfig = [
		'threadid' => 0,
		'sourceforumid' => 0,
		'destforumid' => 0,
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
				break;
				
			case 'user_config':
				/** @var \XF\Repository\Node $nodeRepo */
				$nodeRepo = $this->app()->repository('XF:Node');
				$nodes = $nodeRepo->getFullNodeList()->filterViewable();
				
				$params['nodeTree'] = $nodeRepo->createNodeTree($nodes);
				break;
			
			case 'user_config_view':
				$params['thread'] = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
				$params['sourceForum'] = $this->em()->find('XF:Forum', $this->purchase->configuration['sourceforumid'], ['Node']);
				$params['destinationForum'] = $this->em()->find('XF:Forum', $this->purchase->configuration['destforumid'], ['Node']);
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
			'destforumid' => 'uint',
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
		if (empty($configuration['threadid']) || empty($configuration['destforumid']))
		{
			$errors = \XF::phraseDeferred('please_complete_required_fields');
			return false;
		}
		
		$forum = $this->em()->find('XF:Forum', $configuration['destforumid']);
		if (!$forum)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_no_forum_could_be_found_with_id_x', ['forum_id' => $configuration['destforumid']]);
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
		
		if (in_array($thread->node_id, $this->item->code['excludedforums']))
		{
			// Excluded forum
			$errors = \XF::phraseDeferred('dbtech_shop_cannot_move_threads_in_this_forum');
			return false;
		}
		
		if ($this->item->code['onlyown']
			&& $thread->user_id != $this->purchase->user_id)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_you_can_only_move_your_own_threads');
			return false;
		}
		
		if ($configuration['threadid'] != $this->purchase->configuration['threadid']
			&& $thread->node_id == $forum->node_id
		) {
			// Excluded forum
			$errors = \XF::phraseDeferred('dbtech_shop_thread_already_in_destination');
			return false;
		}
		
		if (empty($this->purchase->configuration['sourceforumid']))
		{
			$configuration['sourceforumid'] = $thread->node_id;
		}
		else
		{
			// Make sure we keep this between reconfigurations
			$configuration['sourceforumid'] = $this->purchase->configuration['sourceforumid'];
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
		
		/** @var \XF\Entity\Forum $sourceForum */
		$sourceForum = $this->em()->find('XF:Forum', $userConfig['sourceforumid'], ['Node']);
		if (!$sourceForum)
		{
			return '';
		}
		
		/** @var \XF\Entity\Forum $destinationForum */
		$destinationForum = $this->em()->find('XF:Forum', $userConfig['destforumid'], ['Node']);
		if (!$destinationForum)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_movethread', [
			'source_forum_url' => $this->app()->router('public')->buildLink('full:' . $sourceForum->Node->getRoute(), $sourceForum),
			'source_forum' => new \XF\PreEscaped($sourceForum->title),
			
			'destination_forum_url' => $this->app()->router('public')->buildLink('full:' . $destinationForum->Node->getRoute(), $destinationForum),
			'destination_forum' => new \XF\PreEscaped($destinationForum->title),
			
			'thread_url' => $this->app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new \XF\PreEscaped($thread->title)
		]);
	}
	
	/**
	 * @param bool $wasConfigured
	 *
	 * @throws \XF\PrintableException
	 */
	protected function afterConfiguration(bool $wasConfigured = false)
	{
		$userConfig = $this->purchase->configuration;
		
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $userConfig['threadid']);
		if (!$thread)
		{
			return;
		}
		
		/** @var \XF\Entity\Forum $targetForum */
		$targetForum = $this->em()->find('XF:Forum', $userConfig['destforumid']);
		if (!$targetForum)
		{
			return;
		}
		
		/** @var \XF\Service\Thread\Mover $mover */
		$mover = $this->service('XF:Thread\Mover', $thread);
		$mover->setSendAlert(true, \XF::phrase('dbtech_shop_moved_via_shop'));
		$mover->move($targetForum);
	}
	
	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		return false;
	}
}