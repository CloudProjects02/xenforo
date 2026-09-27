<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Thread;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\ThreadRepository;

class ThreadBumpHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultUserConfig = [
		'contentid' => 0,
	];


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

	/**
	 * @return string|null
	 */
	public function getAdminConfigTemplate(): ?string
	{
		return null;
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

		return \XF::phrase('dbtech_shop_configuration_notice_threadbump', [
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
		$thread = \XF::app()->em()->find(Thread::class, $this->purchase->configuration['contentid']);
		if (!$thread)
		{
			return;
		}

		$thread->LastPost->post_date = \XF::$time;
		$thread->LastPost->save();

		$thread->last_post_date = \XF::$time;
		$thread->save();
	}
}