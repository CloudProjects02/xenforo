<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Template;

use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\User;

/**
 * @extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
	/**
	 * @param $templater
	 * @param $escape
	 * @param $user
	 * @param bool $withBanner
	 * @param array $attributes
	 *
	 * @return string
	 */
	public function fnUserTitle($templater, &$escape, $user, $withBanner = false, $attributes = [])
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		$userIsValid = ($user instanceof User);
		if (!$userIsValid)
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}

		/** @var \DBTech\Shop\XF\Entity\User $user */

		if (!$user->user_id)
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}

		if (!empty($attributes['preview']))
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}

		$classes = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);

		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('user_title_style_classes', [&$classes]);
		}

		if ($classes)
		{
			// Make sure this is set
			$attributes['class'] = $attributes['class'] ?? '';

			// Ensure we only add the span if needed
			$attributes['class'] .= ' ' . implode(' ', $classes);
		}

		return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
	}

	/**
	 * @param $templater
	 * @param $escape
	 * @param $user
	 * @param bool $includeGroupStyling
	 *
	 * @return string
	 */
	public function fnUsernameClasses($templater, &$escape, $user, $includeGroupStyling = true)
	{
		$parentClasses = parent::fnUsernameClasses($templater, $escape, $user, $includeGroupStyling);

		if (empty($user['user_id']))
		{
			return $parentClasses;
		}

		$userIsValid = ($user instanceof User);
		if (!$userIsValid)
		{
			if (!\XF::options()->dbtech_shop_forceformatting)
			{
				return $parentClasses;
			}

			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = \XF::app()->em()->find(User::class, $user['user_id']);
		}

		if (!$user)
		{
			return $parentClasses;
		}

		$classes = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);

		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('username_style_classes', [&$classes]);
		}

		$escape = false; // note: not doing this explicitly, shouldn't be needed for the output format

		if (empty($classes))
		{
			return $parentClasses;
		}

		return $parentClasses . ' ' . implode(' ', $classes);
	}

	/**
	 * @param $templater
	 * @param $escape
	 * @param $user
	 * @param $size
	 * @param $canonical
	 * @param $attributes
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function fnAvatar($templater, &$escape, $user, $size, $canonical = false, $attributes = [])
	{
		if (empty($user['user_id']))
		{
			return parent::fnAvatar(
				$templater,
				$escape,
				$user,
				$size,
				$canonical,
				$attributes
			);
		}

		$userIsValid = ($user instanceof User);
		if (!$userIsValid)
		{
			if (!\XF::options()->dbtech_shop_forceformatting)
			{
				return parent::fnAvatar(
					$templater,
					$escape,
					$user,
					$size,
					$canonical,
					$attributes
				);
			}

			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = \XF::app()->em()->find(User::class, $user['user_id']);
		}

		if (!$user)
		{
			return parent::fnAvatar(
				$templater,
				$escape,
				$user,
				$size,
				$canonical,
				$attributes
			);
		}

		$classes = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->filterActivePurchasesForUser($user)
		;

		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('avatar_style_classes', [&$classes]);
		}

		if ($classes)
		{
			// Make sure this is set
			$attributes['class'] = $attributes['class'] ?? '';

			// Ensure we only add the span if needed
			$attributes['class'] .= ' ' . implode(' ', $classes);
		}

		return parent::fnAvatar(
			$templater,
			$escape,
			$user,
			$size,
			$canonical,
			$attributes
		);
	}
}