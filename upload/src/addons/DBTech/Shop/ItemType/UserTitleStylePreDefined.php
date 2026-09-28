<?php

namespace DBTech\Shop\ItemType;

/**
 * Class UserTitleStylePreDefined
 *
 * @package DBTech\Shop\ItemType
 */
class UserTitleStylePreDefined extends AbstractHandler
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
	];
	
	
	/**
	 *
	 */
	public function addListeners()
	{
		/**
		 * @param array $styleProps
		 */
		$styleFunc = function (array &$styleProps)
		{
			$adminConfig = $this->item->code;
			
			if ($adminConfig['bold'])
			{
				// Bolded
				$styleProps[] = 'font-weight:bold !important';
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
		
		switch ($context)
		{
			case 'user_config_view':
				$styleProps = [];
				$this->fire('user_title_style_preview', [&$styleProps]);
				
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
			])
			;
	}
}