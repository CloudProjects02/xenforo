<?php

namespace XFRM\Service\ResourceItem;

use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceItem;

class Reassign extends AbstractService
{
	/**
	 * @var ResourceItem
	 */
	protected $resource;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function reassignTo(User $newUser)
	{
		$resource = $this->resource;
		$oldUser = $resource->User;
		$reassigned = ($resource->user_id != $newUser->user_id);

		$resource->user_id = $newUser->user_id;
		$resource->username = $newUser->username;
		$resource->save();

		if ($reassigned && $resource->isVisible() && $this->alert)
		{
			if ($oldUser && \XF::visitor()->user_id != $oldUser->user_id)
			{
				/** @var \XFRM\Repository\ResourceItem $resourceRepo */
				$resourceRepo = $this->repository('XFRM:ResourceItem');
				$resourceRepo->sendModeratorActionAlert(
					$this->resource,
					'reassign_from',
					$this->alertReason,
					['to' => $newUser->username],
					$oldUser
				);
			}

			if (\XF::visitor()->user_id != $newUser->user_id)
			{
				/** @var \XFRM\Repository\ResourceItem $resourceRepo */
				$resourceRepo = $this->repository('XFRM:ResourceItem');
				$resourceRepo->sendModeratorActionAlert(
					$this->resource,
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
