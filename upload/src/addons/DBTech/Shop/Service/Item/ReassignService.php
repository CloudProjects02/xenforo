<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;

class ReassignService extends AbstractService
{
	protected Item $item;
	protected bool $alert = false;
	protected string $alertReason = '';


	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): ReassignService
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}

	/**
	 * @param User $newUser
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function reassignTo(User $newUser): bool
	{
		$item = $this->item;

		$oldUser = $item->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser($item->username);

		$reassigned = ($item->user_id != $newUser->user_id);

		$item->user_id = $newUser->user_id;
		$item->username = $newUser->username;
		$item->save();

		if ($reassigned && $item->isVisible() && $this->alert)
		{
			if (\XF::visitor()->user_id != $oldUser->user_id)
			{
				$itemRepo = \XF::app()->repository(ItemRepository::class);
				$itemRepo->sendModeratorActionAlert(
					$item,
					'reassign_from',
					$this->alertReason,
					['to' => $newUser->username],
					$oldUser
				);
			}

			if (\XF::visitor()->user_id != $newUser->user_id)
			{
				$itemRepo = \XF::app()->repository(ItemRepository::class);
				$itemRepo->sendModeratorActionAlert(
					$item,
					'reassign_to',
					$this->alertReason,
					[],
					$newUser
				);
			}
		}

		return $reassigned;
	}
}