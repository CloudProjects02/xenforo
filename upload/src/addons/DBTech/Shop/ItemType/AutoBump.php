<?php

namespace DBTech\Shop\ItemType;

/**
 * Class AutoBump
 *
 * @package DBTech\Shop\ItemType
 */
class AutoBump extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'interval' => 1
	];

	/** @var array */
	protected $defaultUserConfig = [
		'contentid' => 0,
	];
	
	
	/**
	 *
	 */
	public function addListeners()
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
		
		$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['contentid'], ['Forum']);
		if (!$thread)
		{
			return;
		}
		
		if ($thread->last_post_date >= (
			\XF::$time - ($this->item->code['interval'] * 3600)
		)
		) {
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
		return $this->app()->inputFilterer()->filterArray($config, [
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
		return $this->app()->inputFilterer()->filterArray($input, [
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
			$thread = $this->em()->find('XF:Thread', $configuration['contentid']);
			if (!$thread)
			{
				$errors = \XF::phraseDeferred('no_thread_could_be_found_with_id_x', ['thread_id' => $configuration['contentid']]);
				return false;
			}
		}
		else
		{
			$threadRepo = $this->app()->repository('XF:Thread');
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
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['contentid']);
		if (!$thread)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_autobump', [
			'thread_url' => $this->app()->router('public')->buildLink('full:threads', $thread),
			'thread' => new \XF\PreEscaped($thread->title)
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
		
		switch ($context)
		{
			case 'user_config_view':
				$params['thread'] = $this->em()->find('XF:Thread', $this->purchase->configuration['contentid']);
				break;
		}
		
		return $params;
	}
}