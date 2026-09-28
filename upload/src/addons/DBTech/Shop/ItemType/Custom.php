<?php

namespace DBTech\Shop\ItemType;

/**
 * Class Custom
 *
 * @package DBTech\Shop\ItemType
 */
class Custom extends AbstractHandler implements ConfigurableInterface
{
	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		$adminConfig = [];
		foreach ($config as $key => $item)
		{
			if (empty($item['title']))
			{
				continue;
			}
			
			$adminConfig[$key] = $this->app()->inputFilterer()->filterArray($item, [
				'title' => 'str',
				'required' => 'bool',
			]);
		}
		
		return $adminConfig;
	}
	
	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterUserConfig(array $input = []): array
	{
		$userConfig = [];
		foreach ($this->item->code as $key => $field)
		{
			$userConfig[$key] = isset($input[$key])
				? $this->app()->inputFilterer()->filter($input[$key], 'str')
				: '';
		}
		
		return $userConfig;
	}
	
	/**
	 * @param array $configuration
	 * @param null $errors
	 *
	 * @return bool
	 */
	public function validateUserConfig(array &$configuration = [], &$errors = null): bool
	{
		foreach ($this->item->code as $key => $field)
		{
			if ($field['required']
				&& $configuration[$key] === ''
			) {
				$errors = \XF::phraseDeferred('please_enter_value_for_required_field_x', [
					'field' => $field['title']
				]);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @return string|\XF\PreEscaped
	 */
	public function getConfigurationForConversation(): string
	{
		$lines = [];
		
		foreach ($this->item->code as $key => $field)
		{
			$lines[] = \XF::phrase('dbtech_shop_configuration_notice_custom', [
				'title' => $field['title'],
				'value' => (
					!empty($this->purchase->configuration[$key])
					? new \XF\PreEscaped($this->purchase->configuration[$key])
					: \XF::phrase('dbtech_shop_not_set')
				)
			]);
		}
		
		if (empty($lines))
		{
			return '';
		}
		
		return new \XF\PreEscaped(implode("\n", $lines));
	}
	
	/**
	 * @return string
	 */
	public function getUserConfigTemplate(): ?string
	{
		return (!empty($this->item->code) ? parent::getUserConfigTemplate() : '');
	}
}