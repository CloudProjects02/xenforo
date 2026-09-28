<?php

namespace DBTech\Shop\ItemType;

/**
 * Class PostStylePreDefined
 *
 * @package DBTech\Shop\ItemType
 */
class PostStylePreDefined extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'bold'         => false,
		'italic'       => false,
		'underline'    => false,
		'color'        => '',
		'glow_onoff'   => false,
		'glow'         => '',
		'shadow_onoff' => false,
		'shadow'       => '',
		'extra'        => '',
		'singleuse'    => false
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
			
			if ($adminConfig['bold'])
			{
				// Bolded
				$styleProps[] = 'font-weight:bold';
			}
			
			if ($adminConfig['italic'])
			{
				// Italic
				$styleProps[] = 'font-style:italic';
			}
			
			if ($adminConfig['underline'])
			{
				// Underline
				$styleProps[] = 'text-decoration:underline';
			}
			
			if ($adminConfig['color'])
			{
				// Coloured
				$styleProps[] = 'color:' . $adminConfig['color'];
			}
			
			if ($adminConfig['glow_onoff'] && $adminConfig['glow'])
			{
				// The glow of delight
				$styleProps[] = 'text-shadow: 0px 0px 0.2em ' . $adminConfig['glow'] . ', 0px 0px 0.2em ' . $adminConfig['glow'] . ', 0px 0px 0.2em ' . $adminConfig['glow'];
			}
			
			if ($adminConfig['shadow_onoff'] && $adminConfig['glow'])
			{
				// Apply text shadows
				$styleProps[] = 'text-shadow:2px 2px 4px ' . $adminConfig['shadow'];
			}

			if ($adminConfig['extra'])
			{
				$styleProps[] = $adminConfig['extra'];
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
				// Create a "fake post" to satisfy the requirement for the listener
				
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
		return $this->app()
			->inputFilterer()
			->filterArray($config, [
				'bold'         => 'bool',
				'italic'       => 'bool',
				'underline'    => 'bool',
				'color'        => 'str',
				'glow_onoff'   => 'bool',
				'glow'         => 'str',
				'shadow_onoff' => 'bool',
				'shadow'       => 'str',
				'extra'        => 'str',
				'singleuse'    => 'bool',
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
		$userConfig = $this->purchase->configuration;
		
		/** @var \XF\Entity\Post $post */
		$post = $this->em()->find('XF:Post', $userConfig['contentid']);
		if (!$post)
		{
			return '';
		}
		
		return \XF::phrase('dbtech_shop_configuration_notice_poststyle2_singleuse', [
			'post_url' => $this->app()->router('public')->buildLink('full:posts', $post),
			'post' => $post->post_id
		]);
	}
}