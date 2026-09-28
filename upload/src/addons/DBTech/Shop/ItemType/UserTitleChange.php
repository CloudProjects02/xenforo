<?php

namespace DBTech\Shop\ItemType;

/**
 * Class UserTitleChange
 *
 * @package DBTech\Shop\ItemType
 */
class UserTitleChange extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected $defaultUserConfig = [
		'usertitle' => ''
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
			'usertitle' => 'str',
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
		if (empty($configuration['usertitle']))
		{
			$errors = \XF::phraseDeferred('please_complete_required_fields');
			return false;
		}
		
		$user = $this->purchase->User;
		$user->custom_title = $configuration['usertitle'];
		
		if (!$user->preSave())
		{
			$errors = $user->getErrors();
			return false;
		}
		
		$user->reset();
		
		return true;
	}
	
	/**
	 * @return string
	 */
	public function getConfigurationForConversation(): string
	{
		$userConfig = $this->purchase->configuration;
		
		return \XF::phrase('dbtech_shop_configuration_notice_usertitlechange', [
			'usertitle' => $userConfig['usertitle']
				? new \XF\PreEscaped($userConfig['usertitle'])
				: \XF::phrase('dbtech_shop_not_set'),
		]);
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function activateAlways()
	{
		if ($this->purchase->configuration['usertitle'])
		{
			$user = $this->purchase->User;
			$user->setOption('admin_edit', false);
			$user->custom_title = $this->purchase->configuration['usertitle'];

			if ($user->preSave())
			{
				$user->save();
			}
			else
			{
				$user->reset();
			}
		}
	}
	
	/**
	 * @param null $error
	 *
	 * @throws \XF\PrintableException
	 */
	protected function _deactivate(&$error = null)
	{
		$user = $this->purchase->User;
		$user->custom_title = '';
		
		if ($user->preSave())
		{
			$user->save();
		}
		else
		{
			$user->reset();
		}
	}
}