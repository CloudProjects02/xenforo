<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Post;
use XF\PreEscaped;
use XF\Util\Color;

class PostStyleHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'value'     => [
			'bold'      => false,
			'italic'    => false,
			'underline' => false,
			'color'     => false,
			'glow'      => false,
			'shadow'    => false,
		],
		'singleuse' => false,
	];

	/** @var array */
	protected array $defaultUserConfig = [
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
	public function addListeners(): void
	{
		/**
		 * DEVELOPER NOTES
		 *
		 * Don't assume that $post is a valid post record for a post that exists.
		 * It may be an empty, unsaved entity, which indicates it's a preview, either in the actual post
		 * or in the "User config view" template
		 *
		 * @param Post $post
		 * @param array $styleProps
		 * @param bool $preview
		 */
		$styleFunc = function (Post $post, array &$styleProps, bool $preview = false)
		{
			if (!$preview
				&& (
					$this->item->code['singleuse']
					&& (
						$post->post_id != $this->purchase->configuration['contentid']
						|| empty($this->purchase->configuration['contentid'])
					)
				)
			)
			{
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

			// Needed because of glow+shadow combo
			$textShadows = [];

			if ($adminConfig['value']['glow'] && $userConfig['glow'])
			{
				// The glow of delight
				$textShadows[] = '0px 0px 0.2em ' . $userConfig['glow'];
				$textShadows[] = '0px 0px 0.2em ' . $userConfig['glow'];
				$textShadows[] = '0px 0px 0.2em ' . $userConfig['glow'];
			}

			if ($adminConfig['value']['shadow'] && $userConfig['shadow'])
			{
				// Apply text shadows
				$textShadows[] = '2px 2px 4px ' . $userConfig['shadow'];
			}

			if (\count($textShadows))
			{
				$styleProps[] = 'text-shadow: ' . implode(', ', $textShadows);
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

		/** @var Post $tmp */
		if ($context == 'user_config_view')
		{
			$tmp = \XF::app()->em()->create(Post::class);

			$styleProps = [];
			$this->fire('post_message_markup_preview', [$tmp, &$styleProps, true]);

			$params['styleProps'] = implode('; ', $styleProps);
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
		return \XF::app()->inputFilterer()->filterArray($input, [
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
			if ($configuration['color'] !== '' && !Color::isValidColor($configuration['color']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_color'),
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
			if ($configuration['glow'] !== '' && !Color::isValidColor($configuration['glow']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_glow_color'),
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
			if ($configuration['shadow'] !== '' && !Color::isValidColor($configuration['shadow']))
			{
				$errors = \XF::phraseDeferred('dbtech_shop_please_enter_valid_value_for_field_x', [
					'field' => \XF::phraseDeferred('dbtech_shop_shadow_color'),
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
				$post = \XF::app()->em()->find(Post::class, $configuration['contentid']);
				if (!$post)
				{
					$errors = \XF::phraseDeferred('dbtech_shop_no_post_could_be_found_with_id_x', ['post_id' => $configuration['contentid']]);
					return false;
				}
			}
			else
			{
				$routePath = \XF::app()->request()->getRoutePathFromUrl($configuration['contentid']);
				$routeMatch = \XF::app()->router('public')->routeToController($routePath);
				$params = $routeMatch->getParameterBag();

				if (!$params->post_id)
				{
					$errors = \XF::phraseDeferred('dbtech_shop_no_post_id_could_be_found_from_that_url');
					return false;
				}

				$post = \XF::app()->em()->find(Post::class, $params->post_id);
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
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$adminConfig = $this->item->code['value'];
		$userConfig = $this->purchase->configuration;

		$params = [
			'bold' => ($adminConfig['bold'] && $userConfig['bold']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'italic' => ($adminConfig['italic'] && $userConfig['italic']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'underline' => ($adminConfig['underline'] && $userConfig['underline']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'color' => ($adminConfig['color'] && $userConfig['color']) ? $userConfig['color'] : \XF::phrase('dbtech_shop_not_set'),
			'glow' => ($adminConfig['glow'] && $userConfig['glow']) ? $userConfig['glow'] : \XF::phrase('dbtech_shop_not_set'),
			'shadow' => ($adminConfig['shadow'] && $userConfig['shadow']) ? $userConfig['shadow'] : \XF::phrase('dbtech_shop_not_set'),
		];

		if ($this->item->code['singleuse'])
		{
			$post = \XF::app()->em()->find(Post::class, $userConfig['contentid']);
			if (!$post)
			{
				return '';
			}

			$params['post_url'] = \XF::app()->router('public')->buildLink('full:posts', $post);
			$params['post'] = $post->post_id;
		}

		return \XF::phrase(
			'dbtech_shop_configuration_notice_poststyle' . ($this->item->code['singleuse'] ? '_singleuse' : ''),
			$params
		);
	}
}