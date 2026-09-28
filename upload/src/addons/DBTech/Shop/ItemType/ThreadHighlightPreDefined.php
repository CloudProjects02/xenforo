<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ThreadHighlightPreDefined
 *
 * @package DBTech\Shop\ItemType
 */
class ThreadHighlightPreDefined extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'color'          => '',
		'extra'          => '',
		'excludedforums' => [],
		'singleuse'      => false
	];

	/** @var array */
	protected $defaultUserConfig = [
		'contentid' => 0
	];
	
	
	/**
	 *
	 */
	public function addListeners()
	{
		/**
		 * DEVELOPER NOTES
		 *
		 * Don't assume that $thread is a valid thread record for a thread that exists.
		 * It may be an empty, unsaved entity, which indicates it's a preview, either in the actual thread
		 * or in the "User config view" template
		 *
		 * @param \XF\Entity\Thread $thread
		 * @param array $styleProps
		 * @param bool $transparent
		 * @param bool $preview
		 */
		$styleFunc = function (\XF\Entity\Thread $thread, array &$styleProps, bool $transparent, bool $preview = false)
		{
			if (!$preview)
			{
				if ($this->item->code['singleuse']
					&& (
						$thread->thread_id != $this->purchase->configuration['contentid']
						|| empty($this->purchase->configuration['contentid'])
					)
				) {
					// Item is single use and no content ID was added
					return;
				}

				if (in_array($thread->node_id, $this->item->code['excludedforums']))
				{
					// Thread is in an excluded forum
					return;
				}
			}
			
			$adminConfig = $this->item->code;
			
			if ($adminConfig['color'])
			{
				if ($transparent)
				{
					$styleProps[] = "background: transparent";
				}
				else
				{
					$rgb = \XF\Util\Color::colorToRgb($adminConfig['color']);
					if ($rgb === null)
					{
						return;
					}
					
					$rgb = implode(',', $rgb);
					
					$styleProps[] = "background: rgb({$rgb})";
					$styleProps[] = "background: -webkit-linear-gradient(right, rgba({$rgb},0),rgba({$rgb},1))";
					$styleProps[] = "background: -o-linear-gradient(left, rgba({$rgb},0),rgba({$rgb},1))";
					$styleProps[] = "background: -moz-linear-gradient(left, rgba({$rgb},0),rgba({$rgb},1))";
					$styleProps[] = "background: linear-gradient(to left, rgba({$rgb},0), rgba({$rgb},1))";
				}
			}

			if ($adminConfig['extra'])
			{
				$styleProps[] = $adminConfig['extra'];
			}
		};
		
		// Always allow the preview, even if item is not active
		$this->addListener('thread_bit_markup_preview', $styleFunc);
		
		if ($this->purchase->isActive())
		{
			$hint = $this->item->code['singleuse'] ? $this->purchase->configuration['contentid'] : '_';
			$this->addListener('thread_bit_markup', $styleFunc, $hint);
		}
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
				/** @var \XF\Entity\Thread $tmp */
				$tmp = $this->em()->create('XF:Thread');
				
				$styleProps = [];
				$this->fire('thread_bit_markup_preview', [$tmp, &$styleProps, false, true]);
				
				$params['styleProps'] = implode('; ', $styleProps);
				
				if ($this->item->code['singleuse'])
				{
					$params['thread'] = $this->em()->find('XF:Thread', $this->purchase->configuration['contentid']);
				}
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
			'color'          => 'str',
			'extra'          => 'str',
			'excludedforums' => 'array-uint',
			'singleuse' => 'bool',
		])
			;
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
		
		if ($this->item->code['singleuse'])
		{
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

			if ($thread->user_id != $this->purchase->user_id)
			{
				// Excluded forum
				$errors = \XF::phraseDeferred('dbtech_shop_can_only_affect_your_own_threads');
				return false;
			}

			if (in_array($thread->node_id, $this->item->code['excludedforums']))
			{
				// Excluded forum
				$errors = \XF::phraseDeferred('dbtech_shop_cannot_apply_to_threads_in_this_forum');
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		if ($this->item->code['singleuse'])
		{
			/** @var \XF\Entity\Thread $thread */
			$thread = $this->em()->find('XF:Thread', $this->purchase->configuration['contentid']);
			if (!$thread)
			{
				return '';
			}
			
			return \XF::phrase('dbtech_shop_configuration_notice_threadhighlight2', [
				'thread_url' => $this->app()->router('public')->buildLink('full:threads', $thread),
				'thread' => new \XF\PreEscaped($thread->title)
			]);
		}
		
		return '';
	}
	
	/**
	 * @return string
	 */
	public function getUserConfigTemplate(): ?string
	{
		return ($this->item->code['singleuse'] ? parent::getUserConfigTemplate() : '');
	}
}