<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ThreadTitleStyle
 *
 * @package DBTech\Shop\ItemType
 */
class ThreadTitleStyle extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'value'          => [
			'bold'      => false,
			'italic'    => false,
			'underline' => false,
			'color'     => false,
			'glow'      => false,
			'shadow'    => false,
		],
		'excludedforums' => [],
		'singleuse'      => false
	];

	/** @var array */
	protected $defaultUserConfig = [
		'contentid' => 0,
		'bold'      => false,
		'italic'    => false,
		'underline' => false,
		'color'     => '',
		'glow'      => '',
		'shadow'    => '',
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
		 * @param bool $preview
		 */
		$styleFunc = function (\XF\Entity\Thread $thread, array &$styleProps, bool $preview = false)
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
			$userConfig = $this->purchase->configuration;
			
			if ($adminConfig['value']['bold'] && $userConfig['bold'])
			{
				// Bolded
				$styleProps[] = 'font-weight:bold';
			}
			
			if ($adminConfig['value']['italic'] && $userConfig['italic'])
			{
				// Italic
				$styleProps[] = 'font-style:italic';
			}
			
			if ($adminConfig['value']['underline'] && $userConfig['underline'])
			{
				// Underline
				$styleProps[] = 'text-decoration:underline';
			}
			
			if ($adminConfig['value']['color'] && $userConfig['color'])
			{
				// Coloured
				$styleProps[] = 'color:' . $userConfig['color'];
			}
			
			if ($adminConfig['value']['glow'] && $userConfig['glow'])
			{
				// The glow of delight
				$styleProps[] = 'text-shadow: 0px 0px 0.2em ' . $userConfig['glow'] . ', 0px 0px 0.2em ' . $userConfig['glow'] . ', 0px 0px 0.2em ' . $userConfig['glow'];
			}
			
			if ($adminConfig['value']['shadow'] && $userConfig['shadow'])
			{
				// Apply text shadows
				$styleProps[] = 'text-shadow:2px 2px 4px ' . $userConfig['shadow'];
			}
		};
		
		// Always allow the preview, even if item is not active
		$this->addListener('thread_title_markup_preview', $styleFunc);
		
		if ($this->purchase->isActive())
		{
			$hint = $this->item->code['singleuse'] ? $this->purchase->configuration['contentid'] : '_';
			$this->addListener('thread_title_markup', $styleFunc, $hint);
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
				// Create a "fake thread" to satisfy the requirement for the listener
				
				/** @var \XF\Entity\Thread $tmp */
				$tmp = $this->em()->create('XF:Thread');
				
				$styleProps = [];
				$this->fire('thread_title_markup_preview', [$tmp, &$styleProps, true]);
				
				$params['styleProps'] = implode('; ', $styleProps);
				
				if ($this->item->code['singleuse'] && $this->purchase->configuration['contentid'])
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
			'value'          => 'array-bool',
			'excludedforums' => 'array-uint',
			'singleuse'      => 'bool',
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
			'bold'      => 'bool',
			'italic'    => 'bool',
			'underline' => 'bool',
			'color'     => 'str',
			'glow'      => 'str',
			'shadow'    => 'str',
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
		$adminConfig = $this->item->code['value'];
		
		if (!$adminConfig['bold'])
		{
			// Reset to default if it's not enabled
			$configuration['bold'] = false;
		}
		
		if (!$adminConfig['italic'])
		{
			// Reset to default if it's not enabled
			$configuration['italic'] = false;
		}
		
		if (!$adminConfig['underline'])
		{
			// Reset to default if it's not enabled
			$configuration['underline'] = false;
		}
		
		if ($adminConfig['color'])
		{
			if ($configuration['color'] !== '' && !\XF\Util\Color::isValidColor($configuration['color']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_color')
				]);
				return false;
			}
		}
		else
		{
			// Reset to default if it's not enabled
			$configuration['color'] = '';
		}
		
		if ($adminConfig['glow'])
		{
			if ($configuration['glow'] !== '' && !\XF\Util\Color::isValidColor($configuration['glow']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_glow_color')
				]);
				return false;
			}
		}
		else
		{
			// Reset to default if it's not enabled
			$configuration['glow'] = '';
		}
		
		if ($adminConfig['shadow'])
		{
			if ($configuration['shadow'] !== '' && !\XF\Util\Color::isValidColor($configuration['shadow']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_shadow_color')
				]);
				return false;
			}
		}
		else
		{
			// Reset to default if it's not enabled
			$configuration['shadow'] = '';
		}
		
		if ($this->item->code['singleuse'])
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
		$adminConfig = $this->item->code['value'];
		$userConfig = $this->purchase->configuration;
		
		$params = [
			'bold' => ($adminConfig['bold'] && $userConfig['bold']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'italic' => ($adminConfig['italic'] && $userConfig['italic']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'underline' => ($adminConfig['underline'] && $userConfig['underline']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'color' => ($adminConfig['color'] && $userConfig['color']) ? $userConfig['color'] : \XF::phrase('dbtech_shop_not_set'),
			'glow' => ($adminConfig['glow'] && $userConfig['glow']) ? $userConfig['glow'] : \XF::phrase('dbtech_shop_not_set'),
			'shadow' => ($adminConfig['shadow'] && $userConfig['shadow']) ? $userConfig['shadow'] : \XF::phrase('dbtech_shop_not_set')
		];
		
		if ($this->item->code['singleuse'])
		{
			/** @var \XF\Entity\Thread $thread */
			$thread = $this->em()->find('XF:Thread', $userConfig['contentid']);
			if (!$thread)
			{
				return '';
			}
			
			$params['thread_url'] = $this->app()->router('public')->buildLink('full:threads', $thread);
			$params['thread'] = new \XF\PreEscaped($thread->title);
		}
		
		return \XF::phrase(
			'dbtech_shop_configuration_notice_threadtitlestyle' . ($this->item->code['singleuse'] ? '_singleuse' : ''),
			$params
		);
	}
}