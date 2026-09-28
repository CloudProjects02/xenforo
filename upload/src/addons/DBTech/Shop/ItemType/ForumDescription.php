<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ForumDescription
 *
 * @package DBTech\Shop\ItemType
 */
class ForumDescription extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'excludedforums' => [],
		'allowchange' => 0
	];

	/** @var array */
	protected $defaultUserConfig = [
		'forumid' => 0,
		'description' => ''
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
				
				if (!$this->item->code['allowchange'] && $this->purchase->configured)
				{
					$params['forum'] = $this->em()->find('XF:Forum', $this->purchase->configuration['forumid'], ['Node']);
				}
				break;
				
			case 'user_config_view':
				$params['forum'] = $this->em()->find('XF:Forum', $this->purchase->configuration['forumid'], ['Node']);
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
			'allowchange' => 'bool',
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
			'forumid' => 'uint',
			'description' => 'str'
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
		if ($this->item->code['allowchange'] || !$this->purchase->configured)
		{
			$forum = $this->em()->find('XF:Thread', $configuration['forumid']);
			if (!$forum)
			{
				$errors = \XF::phraseDeferred('dbtech_shop_no_forum_could_be_found_with_id_x', ['forum_id' => $configuration['forumid']]);
				return false;
			}
			
			if (in_array($forum->node_id, $this->item->code['excludedforums']))
			{
				// Excluded forum
				$errors = \XF::phraseDeferred('dbtech_shop_cannot_change_this_forums_description');
				return false;
			}
		}
		else
		{
			$configuration['forumid'] = $this->purchase->configuration['forumid'];
		}
		
		$censoredDescription = $this->app()->stringFormatter()->censorText($configuration['description']);
		if ($censoredDescription !== $configuration['description'])
		{
			$errors = \XF::phraseDeferred('dbtech_shop_please_enter_description_that_does_not_contain_any_censored_words');
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
		
		/** @var \XF\Entity\Forum $forum */
		$forum = $this->em()->find('XF:Forum', $userConfig['forumid'], ['Node']);
		if (!$forum)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_forumdescription', [
			'forum_url' => $this->app()->router('public')->buildLink('full:' . $forum->Node->getRoute(), $forum),
			'forum' => new \XF\PreEscaped($forum->title),
			
			'description' => new \XF\PreEscaped($userConfig['description'])
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
		
		/** @var \XF\Entity\Node $node */
		$node = $this->em()->find('XF:Node', $userConfig['forumid']);
		if (!$node)
		{
			return;
		}
		
		$node->description = htmlspecialchars(strval($userConfig['description']), ENT_QUOTES, 'UTF-8', false);
		$node->save();
	}
	
	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		return false;
	}
}