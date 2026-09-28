<?php

namespace XFRM\Service\ResourceRating;

use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceRating;

class Delete extends AbstractService
{
	/**
	 * @var ResourceRating
	 */
	protected $rating;

	/**
	 * @var User|null
	 */
	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ResourceRating $rating)
	{
		parent::__construct($app);
		$this->rating = $rating;
	}

	public function getRating()
	{
		return $this->rating;
	}

	public function setUser(?User $user = null)
	{
		$this->user = $user;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function delete($type, $reason = '')
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

		if ($result && $wasVisible && $this->alert && $this->rating->user_id != $user->user_id)
		{
			/** @var \XFRM\Repository\ResourceRating $ratingRepo */
			$ratingRepo = $this->repository('XFRM:ResourceRating');
			$ratingRepo->sendModeratorActionAlert($this->rating, 'delete', $this->alertReason);
		}

		return $result;
	}
}
