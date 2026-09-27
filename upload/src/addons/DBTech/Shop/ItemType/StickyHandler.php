<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Thread;
use XF\PreEscaped;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadRepository;

class StickyHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'excludedforums' => [],
		'onlyown' => false,
	];

	/** @var array */
	protected array $defaultUserConfig = [
		'threadid' => 0,
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

			case 'user_config_view':
				$params['thread'] = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
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
		)
		{
			// Excluded forum
			$errors = \XF::phraseDeferred('dbtech_shop_thread_already_sticky');
			return false;
		}

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
		if (!$thread)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_sticky', [
			'thread_url' => \XF::app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new PreEscaped($thread->title),
		]);
	}

	/**
	 *
	 */
	protected function activateAlways(): void
	{
		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
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
	protected function _deactivate(&$error = null): void
	{
		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['threadid']);
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