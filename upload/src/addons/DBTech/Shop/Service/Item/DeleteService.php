<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleteService extends AbstractService
{
	protected Item $item;
	protected User $user;
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
		$this->setUser(\XF::visitor());
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?User $user = null): DeleteService
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return null|User
	 */
	public function getUser(): ?User
	{
		return $this->user;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): DeleteService
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}

	/**
	 * @param string $type
	 * @param string $reason
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function delete(string $type, string $reason = ''): bool
	{
		$user = $this->user;
		$wasVisible = $this->item->isVisible();

		if ($type == 'soft')
		{
			$result = $this->item->softDelete($reason, $user);
		}
		else
		{
			// This shouldn't be needed, but it has been an issue... somehow...
			$this->item->reset();

			$result = $this->item->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->item->user_id != $user->user_id)
		{
			$itemRepo = \XF::app()->repository(ItemRepository::class);
			$itemRepo->sendModeratorActionAlert($this->item, 'delete', $this->alertReason);
		}

		return $result;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function unDelete(): bool
	{
		$user = $this->user;
		$wasDeleted = $this->item->item_state == 'deleted';

		$result = $this->item->unDelete($user);

		if ($result && $wasDeleted && $this->alert && $this->item->user_id != $user->user_id)
		{
			$itemRepo = \XF::app()->repository(ItemRepository::class);
			$itemRepo->sendModeratorActionAlert($this->item, 'undelete', $this->alertReason);
		}

		return $result;
	}
}