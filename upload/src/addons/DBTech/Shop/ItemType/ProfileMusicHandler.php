<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;

class ProfileMusicHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultUserConfig = [
		'url' => '',
	];


	/**
	 * @return string|null
	 */
	public function getAdminConfigTemplate(): ?string
	{
		return null;
	}

	/**
	 * @param array $input
	 *
	 * @return array
	 */
	public function filterUserConfig(array $input = []): array
	{
		return \XF::app()->inputFilterer()->filterArray($input, [
			'url' => 'str',
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
		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		return \XF::phrase('dbtech_shop_configuration_notice_profilemusic', [
			'url' => $userConfig['url'] ?: '',
			'url_text' => $userConfig['url']
				? new PreEscaped($userConfig['url'])
				: \XF::phrase('dbtech_shop_not_set'),
		]);
	}
}