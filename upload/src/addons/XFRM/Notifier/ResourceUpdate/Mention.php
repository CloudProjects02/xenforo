<?php

namespace XFRM\Notifier\ResourceUpdate;

use XF\App;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;
use XFRM\Entity\ResourceUpdate;

class Mention extends AbstractNotifier
{
	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	public function __construct(App $app, ResourceUpdate $update)
	{
		parent::__construct($app);

		$this->update = $update;
	}

	public function canNotify(User $user)
	{
		return ($this->update->isVisible() && $user->user_id != $this->update->Resource->user_id);
	}

	public function sendAlert(User $user)
	{
		$update = $this->update;
		$resource = $update->Resource;

		return $this->basicAlert(
			$user,
			$resource->user_id,
			$resource->username,
			'resource_update',
			$update->resource_update_id,
			'mention'
		);
	}
}
