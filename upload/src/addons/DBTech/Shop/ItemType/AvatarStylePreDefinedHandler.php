<?php

namespace DBTech\Shop\ItemType;

class AvatarStylePreDefinedHandler extends AbstractHandler
{
	/** @var array */
	protected array $defaultAdminConfig = [
		'shadow_onoff' => false,
		'shadow'       => '',
		'extra'        => '',
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

			// Needed because of glow+shadow combo
			$boxShadows = [];

			if ($adminConfig['shadow_onoff'] && $adminConfig['shadow'])
			{
				// Apply text shadows
				$boxShadows[] = '0px 0px 12px 2px ' . $adminConfig['shadow'];
			}

			if (\count($boxShadows))
			{
				$styleProps[] = 'box-shadow: ' . implode(', ', $boxShadows);
			}

			if ($adminConfig['extra'])
			{
				$styleProps[] = $adminConfig['extra'];
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
		return $this->app()
			->inputFilterer()
			->filterArray($config, [
				'shadow_onoff' => 'bool',
				'shadow'       => 'str',
				'extra'        => 'str',
			])
		;
	}
}