<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;
use XF\Util\Color;

class UserTitleStyleHandler extends AbstractHandler implements ConfigurableInterface
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
	];

	/** @var array */
	protected array $defaultUserConfig = [
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
		 * @param array $styleProps
		 */
		$styleFunc = function (array &$styleProps)
		{
			$adminConfig = $this->item->code;
			$userConfig = $this->purchase->configuration;

			if ($adminConfig['value']['bold'] && $userConfig['bold'])
			{
				// Bolded
				$styleProps[] = 'font-weight:bold !important';
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
		$this->addListener('user_title_style_preview', $styleFunc);

		if ($this->purchase->isActive())
		{
			$this->addListener('user_title_style', $styleFunc, $this->purchase->user_id);

			$this->addListener('user_title_style_classes', function (&$classes)
			{
				$classes[] = 'userTitle--dbtechShopStyle' . $this->purchase->purchase_id;
			});
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

		if ($context == 'user_config_view')
		{
			$styleProps = [];
			$this->fire('user_title_style_preview', [&$styleProps]);

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

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$adminConfig = $this->item->code['value'];
		$userConfig = $this->purchase->configuration;

		return \XF::phrase('dbtech_shop_configuration_notice_usertitlestyle', [
			'bold' => ($adminConfig['bold'] && $userConfig['bold']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'italic' => ($adminConfig['italic'] && $userConfig['italic']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'underline' => ($adminConfig['underline'] && $userConfig['underline']) ? \XF::phrase('yes') : \XF::phrase('no'),
			'color' => ($adminConfig['color'] && $userConfig['color']) ? $userConfig['color'] : \XF::phrase('dbtech_shop_not_set'),
			'glow' => ($adminConfig['glow'] && $userConfig['glow']) ? $userConfig['glow'] : \XF::phrase('dbtech_shop_not_set'),
			'shadow' => ($adminConfig['shadow'] && $userConfig['shadow']) ? $userConfig['shadow'] : \XF::phrase('dbtech_shop_not_set'),
		]);
	}
}