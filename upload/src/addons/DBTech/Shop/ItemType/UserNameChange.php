<?php

namespace DBTech\Shop\ItemType;

/**
 * Class UserNameChange
 *
 * @package DBTech\Shop\ItemType
 */
class UserNameChange extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultUserConfig = [
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
		return $this->app()->inputFilterer()->filterArray($input, [
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
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		$userConfig = $this->purchase->configuration;
		
		return \XF::phrase('dbtech_shop_configuration_notice_usernamechange', [
			'new_username' => $userConfig['username'] ? new \XF\PreEscaped($userConfig['username']) : \XF::phrase('dbtech_shop_not_set'),
			'old_username' => $userConfig['old_username'] ? new \XF\PreEscaped($userConfig['old_username']) : \XF::phrase('dbtech_shop_not_set'),
		]);
	}
	
	/**
	 * @param bool $wasConfigured
	 *
	 * @throws \XF\PrintableException
	 */
	protected function afterConfiguration(bool $wasConfigured = false)
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