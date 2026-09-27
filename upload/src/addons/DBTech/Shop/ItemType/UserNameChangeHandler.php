<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;
use XF\PrintableException;

class UserNameChangeHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultUserConfig = [
		'username' => '',
		'old_username' => '',
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
			'username' => 'str',
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
		if (empty($configuration['username']))
		{
			$errors = \XF::phraseDeferred('please_complete_required_fields');
			return false;
		}

		$user = $this->purchase->User;
		$user->username = $configuration['username'];

		if (!$user->preSave())
		{
			$errors = $user->getErrors();
			return false;
		}

		$user->reset();

		$configuration['old_username'] = $user->username;

		return true;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		return \XF::phrase('dbtech_shop_configuration_notice_usernamechange', [
			'new_username' => $userConfig['username'] ? new PreEscaped($userConfig['username']) : \XF::phrase('dbtech_shop_not_set'),
			'old_username' => $userConfig['old_username'] ? new PreEscaped($userConfig['old_username']) : \XF::phrase('dbtech_shop_not_set'),
		]);
	}

	/**
	 * @param bool $wasConfigured
	 *
	 * @throws PrintableException
	 */
	protected function afterConfiguration(bool $wasConfigured = false): void
	{
		$user = $this->purchase->User;
		$user->username = $this->purchase->configuration['username'];

		if ($user->preSave())
		{
			$user->save();
		}
		else
		{
			$user->reset();
		}
	}

	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		return false;
	}
}