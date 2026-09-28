<?php

namespace XFRM\EmailStop;

use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XFRM\Repository\CategoryWatch;
use XFRM\Repository\ResourceWatch;

class ResourceItem extends AbstractHandler
{
	public function getStopOneText(User $user, $contentId)
	{
		/** @var \XFRM\Entity\ResourceItem|null $resource */
		$resource = \XF::em()->find('XFRM:ResourceItem', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($resource) { return $resource && $resource->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $resource->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(User $user)
	{
		return \XF::phrase('xfrm_stop_notification_emails_from_all_resources');
	}

	public function stopOne(User $user, $contentId)
	{
		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = \XF::em()->find('XFRM:ResourceItem', $contentId);
		if ($resource)
		{
			/** @var ResourceWatch $resourceWatchRepo */
			$resourceWatchRepo = \XF::repository('XFRM:ResourceWatch');
			$resourceWatchRepo->setWatchState($resource, $user, 'update', ['email_subscribe' => 0]);
		}
	}

	public function stopAll(User $user)
	{
		/** @var ResourceWatch $resourceWatchRepo */
		$resourceWatchRepo = \XF::repository('XFRM:ResourceWatch');
		$resourceWatchRepo->setWatchStateForAll($user, 'update', ['email_subscribe' => 0]);

		/** @var CategoryWatch $categoryWatchRepo */
		$categoryWatchRepo = \XF::repository('XFRM:CategoryWatch');
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}
