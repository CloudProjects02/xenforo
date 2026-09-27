<?php

namespace DBTech\Shop\ItemType;

use XF\PreEscaped;
use XF\PrintableException;

class UserTitleChangeHandler extends AbstractHandler implements ConfigurableInterface
{
	/** @var array */
	protected array $defaultUserConfig = [
		'usertitle' => '',
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
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		$userConfig = $this->purchase->configuration;

		return \XF::phrase('dbtech_shop_configuration_notice_usertitlechange', [
			'usertitle' => $userConfig['usertitle']
				? new PreEscaped($userConfig['usertitle'])
				: \XF::phrase('dbtech_shop_not_set'),
		]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function activateAlways(): void
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
	 * @throws PrintableException
	 */
	protected function _deactivate(&$error = null): void
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