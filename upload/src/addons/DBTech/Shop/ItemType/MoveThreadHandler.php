<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadRepository;
use XF\Service\Thread\MoverService;

class MoveThreadHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'excludedforums' => [],
		'onlyown' => false,
	];

	/** @var array */
	protected array $defaultUserConfig = [
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
				break;

			case 'user_config_view':
				$params['thread'] = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
				$params['sourceForum'] = \XF::app()->em()->find(Forum::class, $this->purchase->configuration['sourceforumid'], ['Node']);
				$params['destinationForum'] = \XF::app()->em()->find(Forum::class, $this->purchase->configuration['destforumid'], ['Node']);
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

		$forum = \XF::app()->em()->find(Forum::class, $configuration['destforumid']);
		if (!$forum)
		{
			$errors = \XF::phraseDeferred('dbtech_shop_no_forum_could_be_found_with_id_x', ['forum_id' => $configuration['destforumid']]);
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
		)
		{
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

		$sourceForum = \XF::app()->em()->find(Forum::class, $userConfig['sourceforumid'], ['Node']);
		if (!$sourceForum)
		{
			return '';
		}

		$destinationForum = \XF::app()->em()->find(Forum::class, $userConfig['destforumid'], ['Node']);
		if (!$destinationForum)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_movethread', [
			'source_forum_url' => \XF::app()->router('public')->buildLink('full:' . $sourceForum->Node->getRoute(), $sourceForum),
			'source_forum' => new PreEscaped($sourceForum->title),

			'destination_forum_url' => \XF::app()->router('public')->buildLink('full:' . $destinationForum->Node->getRoute(), $destinationForum),
			'destination_forum' => new PreEscaped($destinationForum->title),

			'thread_url' => \XF::app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new PreEscaped($thread->title),
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

		$thread = \XF::app()->em()->find(Thread::class, $userConfig['threadid']);
		if (!$thread)
		{
			return;
		}

		$targetForum = \XF::app()->em()->find(Forum::class, $userConfig['destforumid']);
		if (!$targetForum)
		{
			return;
		}

		$mover = \XF::app()->service(MoverService::class, $thread);
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