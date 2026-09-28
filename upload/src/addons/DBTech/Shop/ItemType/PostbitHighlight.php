<?php

namespace DBTech\Shop\ItemType;

/**
 * Class PostbitHighlight
 *
 * @package DBTech\Shop\ItemType
 */
class PostbitHighlight extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultAdminConfig = [
		'singleuse' => false
	];

	/** @var array */
	protected $defaultUserConfig = [
		'contentid' => 0,
		'color'     => '',
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
		 * @param bool $transparent
		 * @param bool $preview
		 */
		$styleFunc = function (\XF\Entity\Post $post, array &$styleProps, bool $transparent, bool $preview = false)
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
			
			$userConfig = $this->purchase->configuration;
			
			if ($userConfig['color'])
			{
				if ($transparent)
				{
					$styleProps[] = "background: transparent";
				}
				else
				{
					$rgb = \XF\Util\Color::colorToRgb($userConfig['color']);
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
		};
		
		// Always allow the preview, even if item is not active
		$this->addListener('post_bit_markup_preview', $styleFunc);
		
		if ($this->purchase->isActive())
		{
			$hint = $this->item->code['singleuse'] ? $this->purchase->configuration['contentid'] : '_';
			$this->addListener('post_bit_markup', $styleFunc, $hint);
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
				$this->fire('post_bit_markup_preview', [$tmp, &$styleProps, false, true]);
				
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
			'color'     => 'str',
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
		if ($configuration['color'] !== '' && !\XF\Util\Color::isValidColor($configuration['color']))
		{
			$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
				'field' => \XF::phraseDeferred('dbtech_shop_color')
			]);
			return false;
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
		$userConfig = $this->purchase->configuration;
		
		$params = [
			'color' => $userConfig['color'] ? $userConfig['color'] : \XF::phrase('dbtech_shop_not_set'),
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
			'dbtech_shop_configuration_notice_postbithighlight' . ($this->item->code['singleuse'] ? '_singleuse' : ''),
			$params
		);
	}
}