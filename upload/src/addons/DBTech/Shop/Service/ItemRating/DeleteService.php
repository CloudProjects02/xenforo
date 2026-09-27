<?php

namespace DBTech\Shop\Service\ItemRating;

use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Repository\ItemRatingRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleteService extends AbstractService
{
	protected ItemRating $rating;
	protected User $user;
	protected bool $alert = false;
	protected string $alertReason = '';

	/**
	 * @param App $app
	 * @param ItemRating $rating
	 */
	public function __construct(App $app, ItemRating $rating)
	{
		parent::__construct($app);
		$this->rating = $rating;
		$this->setUser(\XF::visitor());
	}

	/**
	 * @return ItemRating
	 */
	public function getRating(): ItemRating
	{
		return $this->rating;
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
	 * @param string $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(string $alert, ?string $reason = null): DeleteService
	{
		$this->alert = (bool) $alert;
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
			$ratingRepo = \XF::app()->repository(ItemRatingRepository::class);
			$ratingRepo->sendModeratorActionAlert($this->rating, 'delete', $this->alertReason);
		}

		return $result;
	}
}