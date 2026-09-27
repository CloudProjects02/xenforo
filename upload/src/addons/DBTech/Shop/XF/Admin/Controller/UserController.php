<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Admin\Controller;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Finder\CurrencyFinder;
use XF\Entity\User;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\Exception;

/**
 * @extends \XF\Admin\Controller\UserController
 */
class UserController extends XFCP_UserController
{
	/**
	 * @param User $user
	 *
	 * @return FormAction
	 * @throws Exception
	 */
	protected function userSaveProcess(User $user)
	{
		$form = parent::userSaveProcess($user);
		$input = $this->filter('shop', 'array');

		$currencies = \XF::app()->finder(CurrencyFinder::class)
			->fetch()
			->filter(function (Currency $currency) use ($input)
			{
				if (!$currency->isActive())
				{
					return null;
				}

				if ($currency->isIntegrated())
				{
					return null;
				}

				if (!isset($input[$currency->currency_id]))
				{
					return null;
				}

				return $currency;
			})
		;

		$form->setup(function () use (&$input, $user, $currencies)
		{
			foreach ($currencies AS $currencyId => $currency)
			{
				if ($user->{$currency->column} != $input[$currencyId])
				{
					$user->{$currency->column} = $input[$currencyId];
				}
			}
		});

		return $form;
	}
}