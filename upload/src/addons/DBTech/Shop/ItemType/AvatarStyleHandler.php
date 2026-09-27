<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;
use XF\Util\Color;

class AvatarStyleHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'value'     => [
			'shadow'    => false,
		],
	];

	/** @var array */
	protected array $defaultUserConfig = [
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

			// Needed because of glow+shadow combo
			$boxShadows = [];

			if ($adminConfig['value']['shadow'] && $userConfig['shadow'])
			{
				// Apply box shadows
				$boxShadows[] = '0px 0px 12px 2px ' . $userConfig['shadow'];
			}

			if (\count($boxShadows))
			{
				$styleProps[] = 'box-shadow: ' . implode(', ', $boxShadows);
			}
		};

		// Always allow the preview, even if item is not active
		$this->addListener('avatar_style_preview', $styleFunc);

		if ($this->purchase->isActive())
		{
			$this->addListener('avatar_style', $styleFunc, $this->purchase->user_id);

			$this->addListener('avatar_style_classes', function (&$classes)
			{
				$classes[] = 'avatar--dbtechShopStyle' . $this->purchase->purchase_id;
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
			$this->fire('avatar_style_preview', [&$styleProps]);

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

		return \XF::phrase('dbtech_shop_configuration_notice_avatarstyle', [
			'shadow' => ($adminConfig['shadow'] && $userConfig['shadow'])
				? $userConfig['shadow']
				: \XF::phrase('dbtech_shop_not_set'),
		]);
	}
}