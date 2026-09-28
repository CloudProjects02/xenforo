<?php

namespace DBTech\Shop\ItemType;

/**
 * Class PostStyle
 *
 * @package DBTech\Shop\ItemType
 */
class PostStyle extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'value'     => [
			'bold'      => false,
			'italic'    => false,
			'underline' => false,
			'color'     => false,
			'glow'      => false,
			'shadow'    => false,
		],
		'singleuse' => false
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
		 * Don't assume that $post is a valid post record for a post that exists.
		 * It may be an empty, unsaved entity, which indicates it's a preview, either in the actual post
		 * or in the "User config view" template
		 *
		 * @param \XF\Entity\Post $post
		 * @param array $styleProps
		 * @param bool $preview
		 */
		$styleFunc = function (\XF\Entity\Post $post, array &$styleProps, bool $preview = false)
		{
			if (!$preview
				&& (
					$this->item->code['singleuse']
					&& (
						$post->post_id != $this->purchase->configuration['contentid']
						|| empty($this->purchase->configuration['contentid'])
					)
				)
			) {
				// Item is single use and no content ID was added
				return;
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
		$this->addListener('post_message_markup_preview', $styleFunc);
		
		if ($this->purchase->isActive())
		{
			$hint = $this->item->code['singleuse'] ? $this->purchase->configuration['contentid'] : '_';
			$this->addListener('post_message_markup', $styleFunc, $hint);
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
			case 'user_config_view':
				/** @var \XF\Entity\Post $tmp */
				$tmp = $this->em()->create('XF:Post');
				
				$styleProps = [];
				$this->fire('post_message_markup_preview', [$tmp, &$styleProps, true]);
				
				$params['styleProps'] = implode('; ', $styleProps);
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
			'value' => 'array-bool',
			'singleuse' => 'bool',
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
				$post = $this->em()->find('XF:Post', $configuration['contentid']);
				if (!$post)
				{
					$errors = \XF::phraseDeferred('dbtech_shop_no_post_could_be_found_with_id_x', ['post_id' => $configuration['contentid']]);
					return false;
				}
			}
			else
			{
				$routePath = $this->app()->request()->getRoutePathFromUrl($configuration['contentid']);
				$routeMatch = $this->app()->router('public')->routeToController($routePath);
				$params = $routeMatch->getParameterBag();
				
				if (!$params->post_id)
				{
					$errors = \XF::phraseDeferred('dbtech_shop_no_post_id_could_be_found_from_that_url');
					return false;
				}
				
				$post = $this->app()->find('XF:Post', $params->post_id);
				if (!$post)
				{
					$errors = \XF::phraseDeferred('dbtech_shop_no_post_could_be_found_with_id_x', ['post_id' => $params->post_id]);
					return false;
				}
				
				$configuration['contentid'] = $post->post_id;
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
			/** @var \XF\Entity\Post $post */
			$post = $this->em()->find('XF:Post', $userConfig['contentid']);
			if (!$post)
			{
				return '';
			}
			
			$params['post_url'] = $this->app()->router('public')->buildLink('full:posts', $post);
			$params['post'] = $post->post_id;
		}
		
		return \XF::phrase(
			'dbtech_shop_configuration_notice_poststyle' . ($this->item->code['singleuse'] ? '_singleuse' : ''),
			$params
		);
	}
}