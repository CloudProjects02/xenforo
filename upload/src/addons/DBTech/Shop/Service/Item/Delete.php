<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Delete
 *
 * @package DBTech\Shop\Service\Item
 */
class Delete extends \XF\Service\AbstractService
{
	/** @var \DBTech\Shop\Entity\Item */
	protected $item;

	/** @var \XF\Entity\User|null */
	protected $user;
	
	/** @var bool */
	protected $alert = false;

	/** @var string */
	protected $alertReason = '';
	
	/**
	 * Delete constructor.
	 *
	 * @param \XF\App $app
	 * @param Item $item
	 */
	public function __construct(\XF\App $app, Item $item)
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
	 * @param \XF\Entity\User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?\XF\Entity\User $user = null): Delete
	{
		$this->user = $user;

		return $this;
	}
	
	/**
	 * @return null|\XF\Entity\User
	 */
	public function getUser(): ?\XF\Entity\User
	{
		return $this->user;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): Delete
	{
		$this->alert = (bool)$alert;
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
	 * @throws \XF\PrintableException
	 */
	public function delete(string $type, string $reason = ''): bool
	{
		$user = $this->user ?: \XF::visitor();
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
			/** @var \DBTech\Shop\Repository\Item $itemRepo */
			$itemRepo = $this->repository('DBTech\Shop:Item');
			$itemRepo->sendModeratorActionAlert($this->item, 'delete', $this->alertReason);
		}

		return $result;
	}
	
	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function unDelete(): bool
	{
		$user = $this->user ?: \XF::visitor();
		$wasDeleted = $this->item->item_state == 'deleted';
		
		$result = $this->item->unDelete($user);
		
		if ($result && $wasDeleted && $this->alert && $this->item->user_id != $user->user_id)
		{
			/** @var \DBTech\Shop\Repository\Item $itemRepo */
			$itemRepo = $this->repository('DBTech\Shop:Item');
			$itemRepo->sendModeratorActionAlert($this->item, 'undelete', $this->alertReason);
		}
		
		return $result;
	}
}