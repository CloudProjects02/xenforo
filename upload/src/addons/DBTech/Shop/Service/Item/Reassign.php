<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Reassign
 *
 * @package DBTech\Shop\Service\Item
 */
class Reassign extends \XF\Service\AbstractService
{
	/** @var \DBTech\Shop\Entity\Item */
	protected $item;
	
	/** @var bool */
	protected $alert = false;
	
	/** @var string */
	protected $alertReason = '';
	
	
	/**
	 * Reassign constructor.
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
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): Reassign
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}
	
	/**
	 * @param \XF\Entity\User $newUser
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function reassignTo(\XF\Entity\User $newUser): bool
	{
		$item = $this->item;

		/** @var \XF\Entity\User $user */
		$oldUser = $item->User ?: $this->repository('XF:User')->getGuestUser($item->username);
		
		$reassigned = ($item->user_id != $newUser->user_id);
		
		$item->user_id = $newUser->user_id;
		$item->username = $newUser->username;
		$item->save();
		
		if ($reassigned && $item->isVisible() && $this->alert)
		{
			if (\XF::visitor()->user_id != $oldUser->user_id)
			{
				/** @var \DBTech\Shop\Repository\Item $itemRepo */
				$itemRepo = $this->repository('DBTech\Shop:Item');
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
				/** @var \DBTech\Shop\Repository\Item $itemRepo */
				$itemRepo = $this->repository('DBTech\Shop:Item');
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