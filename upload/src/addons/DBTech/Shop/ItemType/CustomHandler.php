<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;

class CustomHandler extends AbstractHandler implements ConfigurableInterface
{
	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		$adminConfig = [];
		foreach ($config AS $key => $item)
		{
			if (empty($item['title']))
			{
				continue;
			}

			$adminConfig[$key] = \XF::app()->inputFilterer()->filterArray($item, [
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
		foreach ($this->item->code AS $key => $field)
		{
			$userConfig[$key] = isset($input[$key])
				? \XF::app()->inputFilterer()->filter($input[$key], 'str')
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
		foreach ($this->item->code AS $key => $field)
		{
			if ($field['required']
				&& $configuration[$key] === ''
			)
			{
				$errors = \XF::phraseDeferred('please_enter_value_for_required_field_x', [
					'field' => $field['title'],
				]);
				return false;
			}
		}

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$lines = [];

		foreach ($this->item->code AS $key => $field)
		{
			$lines[] = \XF::phrase('dbtech_shop_configuration_notice_custom', [
				'title' => $field['title'],
				'value' => (
					!empty($this->purchase->configuration[$key])
					? new PreEscaped($this->purchase->configuration[$key])
					: \XF::phrase('dbtech_shop_not_set')
				),
			]);
		}

		if (empty($lines))
		{
			return '';
		}

		return new PreEscaped(implode("\n", $lines));
	}

	/**
	 * @return string
	 */
	public function getUserConfigTemplate(): string
	{
		return (!empty($this->item->code) ? parent::getUserConfigTemplate() : '');
	}
}