<?php

namespace DBTech\Shop\ItemType;

use XF\Entity\Post;
use XF\PreEscaped;
use XF\Util\Color;

class PostbitHighlightPreDefinedHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'color'     => '',
		'extra'     => '',
		'singleuse' => false,
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
		/**
		 * DEVELOPER NOTES
		 *
		 * Don't assume that $post is a valid post record for a post that exists.
		 * It may be an empty, unsaved entity, which indicates it's a preview, either in the actual post
		 * or in the "User config view" template
		 *
		 * @param Post $post
		 * @param array $styleProps
		 * @param bool $transparent
		 * @param bool $preview
		 */
		$styleFunc = function (Post $post, array &$styleProps, bool $transparent, bool $preview = false)
		{
			if (
				!$preview
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

			if ($adminConfig['color'])
			{
				if ($transparent)
				{
					$styleProps[] = "background: transparent";
				}
				else
				{
					$rgb = Color::colorToRgb($adminConfig['color']);
					if ($rgb === null)
					{
						return;
					}

					$rgb = implode(',', $rgb);

					$styleProps[] = "background: rgb($rgb)";
					$styleProps[] = "background: -webkit-linear-gradient(right, rgba($rgb,0),rgba($rgb,1))";
					$styleProps[] = "background: -o-linear-gradient(left, rgba($rgb,0),rgba($rgb,1))";
					$styleProps[] = "background: -moz-linear-gradient(left, rgba($rgb,0),rgba($rgb,1))";
					$styleProps[] = "background: linear-gradient(to left, rgba($rgb,0), rgba($rgb,1))";
				}
			}

			if ($adminConfig['extra'])
			{
				$styleProps[] = $adminConfig['extra'];
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

		/** @var Post $tmp */
		if ($context == 'user_config_view')
		{
			$tmp = \XF::app()->em()->create(Post::class);

			$styleProps = [];
			$this->fire('post_bit_markup_preview', [$tmp, &$styleProps, false, true]);

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
			'color'     => 'str',
			'extra'     => 'str',
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

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		$post = \XF::app()->em()->find(Post::class, $userConfig['contentid']);
		if (!$post)
		{
			return '';
		}

		return \XF::phrase('dbtech_shop_configuration_notice_postbithighlight2_singleuse', [
			'post_url' => \XF::app()->router('public')->buildLink('full:posts', $post),
			'post' => $post->post_id,
		]);
	}

	/**
	 * @return string
	 */
	public function getUserConfigTemplate(): string
	{
		return ($this->item->code['singleuse'] ? parent::getUserConfigTemplate() : '');
	}
}