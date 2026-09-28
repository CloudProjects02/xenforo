<?php

namespace XFRM\Notifier\ResourceUpdate;

use XF\App;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;
use XFRM\Entity\ResourceUpdate;

use function in_array;

abstract class AbstractWatch extends AbstractNotifier
{
	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	protected $actionType;
	protected $isApplicable;

	abstract protected function getDefaultWatchNotifyData();
	abstract protected function getApplicableActionTypes();
	abstract protected function getWatchEmailTemplateName();

	public function __construct(App $app, ResourceUpdate $update, $actionType)
	{
		parent::__construct($app);

		$this->update = $update;
		$this->actionType = $actionType;
		$this->isApplicable = $this->isApplicable();
	}

	protected function isApplicable()
	{
		if (!in_array($this->actionType, $this->getApplicableActionTypes()))
		{
			return false;
		}

		if (!$this->update->isVisible())
		{
			return false;
		}

		return true;
	}

	public function canNotify(User $user)
	{
		if (!$this->isApplicable)
		{
			return false;
		}

		$update = $this->update;
		$resource = $update->Resource;

		if ($user->user_id == $update->team_user_id || $user->isIgnoring($resource->user_id))
		{
			return false;
		}

		return true;
	}

	public function sendAlert(User $user)
	{
		$update = $this->update;
		$resource = $update->Resource;

		if ($user->user_id == $resource->user_id)
		{
			$senderId = $update->team_user_id;
			$senderName = $update->team_username;
		}
		else
		{
			$senderId = $resource->user_id;
			$senderName = $resource->username;
		}

		return $this->basicAlert(
			$user,
			$senderId,
			$senderName,
			'resource_update',
			$update->resource_update_id,
			'insert'
		);
	}

	public function sendEmail(User $user)
	{
		if (!$user->email || $user->user_state != 'valid')
		{
			return false;
		}

		$update = $this->update;

		$params = [
			'update' => $update,
			'resource' => $update->Resource,
			'category' => $update->Resource->Category,
			'receiver' => $user,
		];

		$template = $this->getWatchEmailTemplateName();

		$this->app()->mailer()->newMail()
			->setToUser($user)
			->setTemplate($template, $params)
			->queue();

		return true;
	}

	public function getDefaultNotifyData()
	{
		if (!$this->isApplicable)
		{
			return [];
		}

		return $this->getDefaultWatchNotifyData();
	}
}
