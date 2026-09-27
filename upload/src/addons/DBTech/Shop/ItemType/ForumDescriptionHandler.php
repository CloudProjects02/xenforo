<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Entity\Thread;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\NodeRepository;

class ForumDescriptionHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'excludedforums' => [],
		'allowchange' => 0,
	];

	/** @var array */
	protected array $defaultUserConfig = [
		'forumid' => 0,
		'description' => '',
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
				break;

			case 'user_config':
				$nodeRepo = \XF::app()->repository(NodeRepository::class);
				$nodes = $nodeRepo->getFullNodeList()->filterViewable();

				$params['nodeTree'] = $nodeRepo->createNodeTree($nodes);

				if (!$this->item->code['allowchange'] && $this->purchase->configured)
				{
					$params['forum'] = \XF::app()->em()->find(Forum::class, $this->purchase->configuration['forumid'], ['Node']);
				}
				break;

			case 'user_config_view':
				$params['forum'] = \XF::app()->em()->find(Forum::class, $this->purchase->configuration['forumid'], ['Node']);
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
		return \XF::app()->inputFilterer()->filterArray($input, [
			'forumid' => 'uint',
			'description' => 'str',
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
			$forum = \XF::app()->em()->find(Thread::class, $configuration['forumid']);
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

		$censoredDescription = \XF::app()->stringFormatter()->censorText($configuration['description']);
		if ($censoredDescription !== $configuration['description'])
		{
			$errors = \XF::phraseDeferred('dbtech_shop_please_enter_description_that_does_not_contain_any_censored_words');
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

		$forum = \XF::app()->em()->find(Forum::class, $userConfig['forumid'], ['Node']);
		if (!$forum)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_forumdescription', [
			'forum_url' => \XF::app()->router('public')->buildLink('full:' . $forum->Node->getRoute(), $forum),
			'forum' => new PreEscaped($forum->title),

			'description' => new PreEscaped($userConfig['description']),
		]);
	}

	/**
	 * @param bool $wasConfigured
	 *
	 * @throws PrintableException
	 */
	protected function afterConfiguration(bool $wasConfigured = false): void
	{
		$userConfig = $this->purchase->configuration;

		$node = \XF::app()->em()->find(Node::class, $userConfig['forumid']);
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