<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Admin\Controller;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Finder\CurrencyFinder;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\User;
use XF\InputFilterer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\Exception as ReplyException;

/**
 * @extends \XF\Admin\Controller\UserController
 */
class UserController extends XFCP_UserController
{
	/**
	 * @param User $user
	 *
	 * @return FormAction
	 * @throws ReplyException
	 */
	protected function userSaveProcess(User $user)
	{
		$form = parent::userSaveProcess($user);
		$input = $this->filter('credits', InputFilterer::ARRAY);

		/** @var AbstractCollection<Currency> $currencies */
		$currencies = \XF::app()->finder(CurrencyFinder::class)
			->fetch()
		;

		$form->validate(function () use (&$input, $user, $currencies)
		{
			foreach ($currencies AS $currency)
			{
				// Make sure there's an adjust event
				$currency->verifyAdjustEvent();

				if (!isset($input[$currency->currency_id]))
				{
					// This was probably a deactivated currency
					unset($input[$currency->currency_id]);

					continue;
				}

				if ($user->{$currency->column} == $input[$currency->currency_id])
				{
					// No change in points
					unset($input[$currency->currency_id]);
				}
			}

			foreach ($input AS $currencyId => $value)
			{
				if (!isset($currencies[$currencyId]))
				{
					// Ignore this currency
					unset($input[$currencyId]);
				}
			}
		});

		$form->complete(function () use ($input, $user, $currencies)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$adjustHandler = $eventTriggerRepo->getHandler('adjust');

			/** @var \DBTech\Credits\XF\Entity\User $visitor */
			$visitor = \XF::visitor();

			foreach ($input AS $currencyId => $value)
			{
				/** @var Currency $currency */
				$currency = $currencies[$currencyId];

				if ($user->{$currency->column} < $value)
				{
					// Adjust event (up)
					$adjustHandler
						->setOption('adminOverride', true)
						->apply($user->user_id, [
							'currency_id' 	=> $currencyId,
							'multiplier' 	=> abs($value - $user->{$currency->column}),
							'message'  		=> \XF::language()->renderPhrase('dbtech_credits_admin_adjust'),
							'source_user_id' => $visitor->user_id,
						], $user);
				}
				else if ($user->{$currency->column} > $value)
				{
					// Adjust event (down)
					$adjustHandler
						->setOption('adminOverride', true)
						->apply($user->user_id, [
							'currency_id' => $currencyId,
							'multiplier' => (-1 * abs($user->{$currency->column} - $value)),
							'message' => \XF::language()->renderPhrase('dbtech_credits_admin_adjust'),
							'source_user_id' => $visitor->user_id,
						], $user);
				}
			}
		});

		return $form;
	}
}