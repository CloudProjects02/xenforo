<?php

namespace DBTech\Shop\Service\ItemRating;

use DBTech\Shop\Entity\ItemRating;

/**
 * Class Delete
 *
 * @package DBTech\Shop\Service\ItemRating
 */
class Delete extends \XF\Service\AbstractService
{
	/** @var ItemRating  */
	protected $rating;

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
	 * @param ItemRating $rating
	 */
	public function __construct(\XF\App $app, ItemRating $rating)
	{
		parent::__construct($app);
		$this->rating = $rating;
	}
	
	/**
	 * @return ItemRating
	 */
	public function getRating(): ItemRating
	{
		return $this->rating;
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
	 * @param string $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(string $alert, ?string $reason = null): Delete
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
		$wasVisible = $this->rating->rating_state == 'visible';

		if ($type == 'soft')
		{
			$result = $this->rating->softDelete($reason, $user);
		}
		else
		{
			$result = $this->rating->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->rating->Item->user_id != $user->user_id)
		{
			/** @var \DBTech\Shop\Repository\ItemRating $ratingRepo */
			$ratingRepo = $this->repository('DBTech\Shop:ItemRating');
			$ratingRepo->sendModeratorActionAlert($this->rating, 'delete', $this->alertReason);
		}

		return $result;
	}
}