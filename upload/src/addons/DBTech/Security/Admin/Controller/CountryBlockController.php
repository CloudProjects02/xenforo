<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use DBTech\Security\Entity\Country;
use DBTech\Security\Repository\CountryRepository;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

class CountryBlockController extends AbstractController
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
	 * @throws PrintableException
	 */
	public function actionIndex(): AbstractReply
	{
		$countries = \XF::app()->repository(CountryRepository::class)
			->findCountriesForList()
			->fetch()
		;

		if ($this->isPost())
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Country> $currentBlocked */
			$currentBlocked = $countries->filter(function (Country $country): ?Country
			{
				if ($country->blocked)
				{
					return $country;
				}

				return null;
			});

			$newBlocked = $this->filter('countries', 'array-str');
			foreach ($currentBlocked AS $country)
			{
				if (!in_array($country->country_code, $newBlocked))
				{
					$country->blocked = false;
					$country->save();
				}
			}

			foreach ($newBlocked AS $countryCode)
			{
				if (!$countries->offsetExists($countryCode))
				{
					continue;
				}

				/** @var Country $country */
				$country = $countries->offsetGet($countryCode);

				if (!$country->blocked)
				{
					$country->blocked = true;
					$country->save();
				}
			}

			return $this->redirect($this->buildLink('dbtech-security/country-block'));
		}

		$viewParams = [
			'countries' => $countries,
		];
		return $this->view(
			View\CountryBlock\IndexView::class,
			'dbtech_security_country_block',
			$viewParams
		);
	}
}