<?php

namespace XFRM\Service\ResourceItem;

use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\FeaturedContent\Creator;
use XF\Service\FeaturedContent\Deleter;
use XF\Service\ModerationAlertSendableTrait;
use XFRM\Entity\Category;
use XFRM\Entity\ResourceItem;
use XFRM\Service\ResourceUpdate\Notify;

use function call_user_func, intval;

class Move extends AbstractService
{
	use ModerationAlertSendableTrait;

	/**
	 * @var ResourceItem
	 */
	protected $resource;

	protected $alert = false;
	protected $alertReason = '';

	protected $notifyWatchers = false;

	protected $prefixId = null;

	protected $extraSetup = [];

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

	public function setPrefix($prefixId)
	{
		$this->prefixId = ($prefixId === null ? $prefixId : intval($prefixId));
	}

	public function setNotifyWatchers($value = true)
	{
		$this->notifyWatchers = (bool) $value;
	}

	public function addExtraSetup(callable $extra)
	{
		$this->extraSetup[] = $extra;
	}

	public function move(Category $category)
	{
		$user = \XF::visitor();

		$resource = $this->resource;
		$oldCategory = $resource->Category;

		$moved = ($resource->resource_category_id != $category->resource_category_id);

		if ($this->alert)
		{
			$wasVisibleForAlert = $this->isContentVisibleToContentAuthor(
				$resource,
				$resource
			);
		}
		else
		{
			$wasVisibleForAlert = false;
		}

		foreach ($this->extraSetup AS $extra)
		{
			call_user_func($extra, $resource, $category);
		}

		$resource->resource_category_id = $category->resource_category_id;
		if ($this->prefixId !== null)
		{
			$resource->prefix_id = $this->prefixId;
		}

		if (!$resource->preSave())
		{
			throw new PrintableException($resource->getErrors());
		}

		$db = $this->db();
		$db->beginTransaction();

		$resource->save(true, false);

		if ($moved)
		{
			if ($resource->isFeatured() && $resource->Feature)
			{
				$feature = $resource->Feature;
				if ($feature->auto_featured && !$category->auto_feature)
				{
					/** @var Deleter $deleter */
					$deleter = $this->service(
						'XF:FeaturedContent\Deleter',
						$feature
					);
					$deleter->delete();
				}
				else
				{
					$feature->fastUpdate(
						'content_container_id',
						$category->resource_category_id
					);
				}
			}
			else if ($category->auto_feature)
			{
				/** @var Creator $creator */
				$creator = $this->service(
					'XF:FeaturedContent\Creator',
					$resource
				);
				$creator->setAutoFeatured();
				$creator->save();
			}
		}

		$db->commit();

		if ($this->alert)
		{
			$isVisibleForAlert = $this->isContentVisibleToContentAuthor(
				$resource,
				$resource
			);
		}
		else
		{
			$isVisibleForAlert = false;
		}

		if (
			$moved &&
			$resource->isVisible() &&
			$this->alert &&
			$resource->user_id !== $user->user_id &&
			($wasVisibleForAlert || $isVisibleForAlert)
		)
		{
			/** @var \XFRM\Repository\ResourceItem $resourceRepo */
			$resourceRepo = $this->repository('XFRM:ResourceItem');
			$resourceRepo->sendModeratorActionAlert($this->resource, 'move', $this->alertReason);
		}

		if ($moved && $this->notifyWatchers)
		{
			/** @var Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $resource->Description, 'resource');
			if ($oldCategory)
			{
				$notifier->skipUsersWatchingCategory($oldCategory);
			}
			$notifier->notifyAndEnqueue(3);
		}

		return $moved;
	}
}
