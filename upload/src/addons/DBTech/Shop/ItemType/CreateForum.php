<?php

namespace DBTech\Shop\ItemType;

/**
 * Class CreateForum
 *
 * @package DBTech\Shop\ItemType
 */
class CreateForum extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'excludedforums' => []
	];

	/** @var array */
	protected $defaultUserConfig = [
		'title' => '',
		'description' => '',
		'parentid' => 0,
		'forumid' => 0
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
			'title' => 'str',
			'description' => 'str',
			'parentid' => 'uint',
		]);
	}
	
	/**
	 * @param array $configuration
	 * @param null $errors
	 *
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function validateUserConfig(array &$configuration = [], &$errors = null): bool
	{
		if (empty($configuration['parentid']))
		{
			$errors = \XF::phraseDeferred('please_select_valid_parent');
			return false;
		}
		
		if (empty($configuration['title']))
		{
			$errors = \XF::phraseDeferred('please_enter_valid_title');
			return false;
		}
		
		if (in_array($configuration['parentid'], $this->item->code['excludedforums']))
		{
			// Excluded forum
			$errors = \XF::phraseDeferred('please_select_valid_forum');
			return false;
		}
		
		if ($this->purchase->configured && !empty($this->purchase->configuration['forumid']))
		{
			// Carry over existing forum ID so we don't lose data
			$configuration['forumid'] = $this->purchase->configuration['forumid'];
		}
		
		$stringFormatter = $this->app()->stringFormatter();
		$configuration['title'] = $stringFormatter->censorText($configuration['title']);
		$configuration['description'] = $stringFormatter->censorText($configuration['description']);
		
		$node = null;
		if (!empty($configuration['forumid']))
		{
			/** @var \XF\Entity\Node $node */
			$node = $this->em()->find('XF:Node', $configuration['forumid']);
		}
		
		/** @var \XF\Entity\Forum $parent */
		$parent = $this->em()->find('XF:Forum', $configuration['parentid'], ['Node']);
		
		if (!$node)
		{
			// Create new forum
			
			/** @var \XF\Entity\Node $node */
			$node = $this->em()->create('XF:Node');
			$node->node_type_id = 'Forum';
			
			$input = [
				'node' => [
					'title' => $configuration['title'],
					'node_name' => '',
					'description' => htmlspecialchars(strval($configuration['description']), ENT_QUOTES, 'UTF-8', false),
					'parent_node_id' => $parent->node_id,
					'display_order' => $parent->Node->display_order,
					'display_in_list' => $parent->Node->display_in_list,
					'style_id' => $parent->Node->style_id,
					'navigation_id' => $parent->Node->navigation_id
				]
			];
			
			/** @var \XF\Entity\Forum $data */
			$data = $node->getDataRelationOrDefault();
			$node->addCascadedSave($data);
			
			$node->bulkSet($input['node']);
			
			
			$forumInput = [
				'allow_posting' => $parent->allow_posting,
				'moderate_threads' => $parent->moderate_threads,
				'moderate_replies' => $parent->moderate_replies,
				'count_messages' => $parent->count_messages,
				'find_new' => $parent->find_new,
				'allowed_watch_notifications' => $parent->allowed_watch_notifications,
				'default_sort_order' => $parent->default_sort_order,
				'default_sort_direction' => $parent->default_sort_direction,
				'list_date_limit_days' => $parent->list_date_limit_days,
				'default_prefix_id' => 0,
				'require_prefix' => false,
				'min_tags' => $parent->min_tags
			];
			
			$data->bulkSet($forumInput);
			
			
			if (!$node->preSave())
			{
				$errors = $node->getErrors();
				return false;
			}
			
			$node->save();
			
			// We finally have our new forum ID
			$configuration['forumid'] = $node->node_id;
		}
		else
		{
			// Edit existing forum
			
			$input = [
				'node' => [
					'title' => $configuration['title'],
					'description' => htmlspecialchars(strval($configuration['description']), ENT_QUOTES, 'UTF-8', false),
					'parent_node_id' => $parent->node_id,
				]
			];
			
			/** @var \XF\Entity\Forum $data */
			$data = $node->getDataRelationOrDefault();
			$node->addCascadedSave($data);
			
			$node->bulkSet($input['node']);
			
			
			if (!$node->preSave())
			{
				$errors = $node->getErrors();
				return false;
			}
			
			$node->save();
		}
		
		return true;
	}
	
	/**
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		/** @var \XF\Entity\Forum $forum */
		$forum = $this->em()->find('XF:Forum', $this->purchase->configuration['forumid'], ['Node', 'Node.Parent']);
		if (!$forum)
		{
			return false;
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_createforum', [
			'forum_url' => $this->app()->router('public')->buildLink('full:' . $forum->Node->getRoute(), $forum),
			'forum' => new \XF\PreEscaped($forum->title),
			'description' => new \XF\PreEscaped($forum->description),
			
			'parent_forum_url' => $this->app()->router('public')->buildLink('full:' .  $forum->Node->Parent->getRoute(), $forum->Node->Parent),
			'parent_forum' => new \XF\PreEscaped($forum->Node->Parent->title)
		]);
	}
	
	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		return false;
	}
}