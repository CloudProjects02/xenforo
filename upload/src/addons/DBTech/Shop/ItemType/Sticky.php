<?php

namespace DBTech\Shop\ItemType;

/**
 * Class Sticky
 *
 * @package DBTech\Shop\ItemType
 */
class Sticky extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'excludedforums' => [],
		'onlyown' => false
	];

	/** @var array */
	protected $defaultUserConfig = [
		'threadid' => 0
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
			
			case 'user_config_view':
				$params['thread'] = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
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
		if (empty($configuration['threadid']))
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
		
		if (in_array($thread->node_id, $this->item->code['excludedforums']))
		{
			// Excluded forum
			$errors = \XF::phraseDeferred('dbtech_shop_cannot_sticky_threads_in_this_forum');
			return false;
		}
		
		if ($this->item->code['onlyown']
			&& $thread->user_id != $this->purchase->user_id)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_you_can_only_sticky_your_own_threads');
			return false;
		}
		
		if ($configuration['threadid'] != $this->purchase->configuration['threadid']
			&& $thread->sticky
		) {
			// Excluded forum
			$errors = \XF::phraseDeferred('dbtech_shop_thread_already_sticky');
			return false;
		}
		
		return true;
	}
	
	/**
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
		if (!$thread)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_sticky', [
			'thread_url' => $this->app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new \XF\PreEscaped($thread->title)
		]);
	}
	
	/**
	 *
	 */
	protected function activateAlways()
	{
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
		if (!$thread)
		{
			return;
		}
		
		if (!$thread->sticky)
		{
			$thread->sticky = true;
			$thread->saveIfChanged();
		}
	}
	
	/**
	 * @param null $error
	 */
	protected function _deactivate(&$error = null)
	{
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['threadid']);
		if (!$thread)
		{
			return;
		}
		
		if ($thread->sticky)
		{
			$thread->sticky = false;
			$thread->saveIfChanged();
		}
	}
}