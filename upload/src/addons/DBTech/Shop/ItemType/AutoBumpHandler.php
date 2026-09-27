<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Thread;
use XF\PreEscaped;
use XF\Repository\ThreadRepository;

class AutoBumpHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'interval' => 1,
	];

	/** @var array */
	protected array $defaultUserConfig = [
		'contentid' => 0,
	];


	/**
	 *
	 */
	public function addListeners(): void
	{
		if (!$this->item->code['interval'])
		{
			return;
		}

		if (!$this->purchase->isActive())
		{
			return;
		}

		if (!$this->purchase->configuration['contentid'])
		{
			return;
		}

		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['contentid'], ['Forum']);
		if (!$thread)
		{
			return;
		}

		if ($thread->last_post_date >= (
			\XF::$time - ($this->item->code['interval'] * 3600)
		)
		)
		{
			// Thread is still new
			return;
		}

		$this->addListener('bump_thread', function () use ($thread)
		{
			$thread->LastPost->post_date = \XF::$time;
			$thread->LastPost->save();

			$thread->last_post_date = \XF::$time;
			$thread->save();
		});
	}

	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($config, [
			'interval' => 'uint',
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
			'contentid' => 'str',
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
		if (empty($configuration['contentid']))
		{
			$errors = \XF::phraseDeferred('please_complete_required_fields');
			return false;
		}

		if (is_numeric($configuration['contentid']))
		{
			$thread = \XF::app()->em()->find(Thread::class, $configuration['contentid']);
			if (!$thread)
			{
				$errors = \XF::phraseDeferred('no_thread_could_be_found_with_id_x', ['thread_id' => $configuration['contentid']]);
				return false;
			}
		}
		else
		{
			$threadRepo = \XF::app()->repository(ThreadRepository::class);
			$thread = $threadRepo->getThreadFromUrl($configuration['contentid'], null, $errors);
			if (!$thread)
			{
				return false;
			}

			$configuration['contentid'] = $thread->thread_id;
		}

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['contentid']);
		if (!$thread)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_autobump', [
			'thread_url' => \XF::app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new PreEscaped($thread->title),
		]);
	}

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		$params = parent::getDefaultTemplateParams($context);

		if ($context == 'user_config_view')
		{
			$params['thread'] = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['contentid']);
		}

		return $params;
	}
}