<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use DBTech\Security\Repository\PasswordRepository;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Searcher\User;

class PasswordController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionForceChange(): AbstractReply
	{
		$viewParams = $this->getSearcherParams(['success' => $this->filter('success', 'bool')]);
		return $this->view(
			View\Password\ForceChangeView::class,
			'dbtech_security_password_change',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionForceReset(): AbstractReply
	{
		$viewParams = $this->getSearcherParams(['success' => $this->filter('success', 'bool')]);
		return $this->view(
			View\Password\ForceResetView::class,
			'dbtech_security_password_reset',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionGenerator(): AbstractReply
	{
		if ($this->isPost())
		{
			$passwordRepo = \XF::app()->repository(PasswordRepository::class);

			$generatedPassword = '';
			if ($this->filter('password_chooser', 'bool'))
			{
				// Generate password
				$generatedPassword = $passwordRepo->generatePassword(
					$this->filter('length', 'uint'),
					$this->filter('rules', 'array-bool')
				);

				$key = $this->filter('username', 'str') . ':' . $passwordRepo->encryptPasswordForBasicAuth($generatedPassword);
			}
			else
			{
				$key = $this->filter('username', 'str') . ':' . $passwordRepo->encryptPasswordForBasicAuth(
					$this->filter('password', 'str')
				);
			}

			$viewParams = [
				'password' => $generatedPassword,
				'key' => $key,
			];
			return $this->view(
				View\Password\Generator\ResultView::class,
				'dbtech_security_password_generator_result',
				$viewParams
			);
		}

		return $this->view(
			View\Password\Generator\FormView::class,
			'dbtech_security_password_generator'
		);
	}

	/**
	 * @param array $extraParams
	 *
	 * @return array
	 */
	protected function getSearcherParams(array $extraParams = []): array
	{
		$searcher = $this->searcher(User::class);

		$viewParams = [
			'criteria'   => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}
}