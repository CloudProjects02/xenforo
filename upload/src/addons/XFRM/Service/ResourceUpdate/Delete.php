<?php

namespace XFRM\Service\ResourceUpdate;

use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceUpdate;

class Delete extends AbstractService
{
	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	/**
	 * @var User|null
	 */
	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ResourceUpdate $update)
	{
		parent::__construct($app);
		$this->update = $update;
	}

	public function getUpdate()
	{
		return $this->update;
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
		$wasVisible = $this->update->message_state == 'visible';

		if ($type == 'soft')
		{
			$result = $this->update->softDelete($reason, $user);
		}
		else
		{
			$result = $this->update->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->update->Resource->user_id != $user->user_id)
		{
			/** @var \XFRM\Repository\ResourceUpdate $updateRepo */
			$updateRepo = $this->repository('XFRM:ResourceUpdate');
			$updateRepo->sendModeratorActionAlert($this->update, 'delete', $this->alertReason);
		}

		return $result;
	}
}
