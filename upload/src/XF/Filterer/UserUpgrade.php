<?php

namespace XF\Filterer;

use XF\Finder\PaymentProfileFinder;
use XF\Finder\UserFinder;
use XF\Finder\UserUpgradeActiveFinder;
use XF\Finder\UserUpgradeFinder;
use XF\Mvc\Entity\Finder;

use function in_array;

class UserUpgrade extends AbstractFilterer
{
	protected $defaultOrder = 'start_date';
	protected $defaultDirection = 'desc';

	protected $validSorts = [
		'username' => 'User.username',
		'start_date' => true,
		'end_date' => true,
	];

	protected function getFinderType(): string
	{
		return $this->setupData['finderType'] ?? UserUpgradeActiveFinder::class;
	}

	protected function initFinder(Finder $finder, array $setupData)
	{
		$finder
			->with(['User', 'PurchaseRequest.PaymentProfile'])
			->with('Upgrade', true);

		if (isset($setupData['defaultOrder']))
		{
			$this->defaultOrder = $setupData['defaultOrder'];
		}

		$finder->setDefaultOrder($this->defaultOrder, $this->defaultDirection);
	}

	protected function getFilterTypeMap(): array
	{
		return [
			'username' => 'str',
			'user_upgrade_id' => 'uint',
			'start_from' => 'datetime',
			'start_to' => 'datetime',
			'end_from' => 'datetime',
			'end_to' => 'datetime',
			'payment_profile_id' => 'uint',
			'order' => 'str',
			'direction' => 'str',
		];
	}

	protected function getLookupTypeList(): array
	{
		return [
			'order',
		];
	}

	protected function onFinalize()
	{
		$finder = $this->finder;

		$sorts = $this->validSorts;
		$order = $this->rawFilters['order'] ?? null;

		if ($order && isset($sorts[$order]))
		{
			$direction = $this->rawFilters['direction'] ?? null;
			if (!in_array($direction, ['asc', 'desc']))
			{
				$direction = 'desc';
			}

			$defaultOrder = $this->defaultOrder;
			$defaultDirection = $this->defaultDirection;

			if ($order != $defaultOrder || $direction != $defaultDirection)
			{
				if ($sorts[$order] === true)
				{
					$finder->order($order, $direction);
				}
				else
				{
					$finder->order($sorts[$order], $direction);
				}

				$this->addLinkParam('order', $order);
				$this->addLinkParam('direction', $direction);
				$this->addDisplayValue('order', $order . '_' . $direction);
			}
		}
	}

	protected function applyFilter(string $filterName, &$value, &$displayValue): bool
	{
		$finder = $this->finder;

		switch ($filterName)
		{
			case 'username':
				$user = $this->app()->finder(UserFinder::class)
					->where('username', $value)
					->fetchOne();
				if (!$user)
				{
					return false;
				}
				$finder->where('user_id', $user->user_id);
				return true;

			case 'user_upgrade_id':
				if (!$value)
				{
					return false;
				}

				$upgrade = $this->app()->finder(UserUpgradeFinder::class)
					->whereId($value)
					->fetchOne();
				if (!$upgrade)
				{
					return false;
				}

				$displayValue = $upgrade->title;
				$finder->where('user_upgrade_id', $value);
				return true;

			case 'start_from':
				if (!$value)
				{
					return false;
				}

				$finder->where('start_date', '>=', $value);
				$displayValue = \XF::app()->language()->date($value, 'picker');
				return true;

			case 'start_to':
				if (!$value)
				{
					return false;
				}

				$finder->where('start_date', '<=', $value + 86400);
				$displayValue = \XF::app()->language()->date($value, 'picker');
				return true;

			case 'end_from':
				if (!$value)
				{
					return false;
				}

				$finder->where('end_date', '>=', $value);
				$displayValue = \XF::app()->language()->date($value, 'picker');
				return true;

			case 'end_to':
				if (!$value)
				{
					return false;
				}

				$finder->where('end_date', '<=', $value + 86400);
				$displayValue = \XF::app()->language()->date($value, 'picker');
				return true;

			case 'payment_profile_id':
				if (!$value)
				{
					return false;
				}

				$profile = $this->app()->finder(PaymentProfileFinder::class)
					->whereId($value)
					->fetchOne();
				if (!$profile)
				{
					return false;
				}

				$displayValue = $profile->title;
				$finder->where('PurchaseRequest.payment_profile_id', $value);
				return true;
		}

		return false;
	}
}
