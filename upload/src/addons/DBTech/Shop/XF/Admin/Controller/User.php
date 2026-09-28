<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Admin\Controller;

class User extends XFCP_User
{
	/**
	 * @param \XF\Entity\User $user
	 *
	 * @return \XF\Mvc\FormAction
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function userSaveProcess(\XF\Entity\User $user)
	{
		$form = parent::userSaveProcess($user);
		$input = $this->filter('shop', 'array');
		
		/** @var \DBTech\Shop\Entity\Currency[] $currencies */
		$currencies = $this->finder('DBTech\Shop:Currency')
			->fetch()
			->filter(function (\DBTech\Shop\Entity\Currency $currency) use ($input)
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
			foreach ($currencies as $currencyId => $currency)
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