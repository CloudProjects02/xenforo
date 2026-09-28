<?php

namespace XFRM\Service\ResourceItem;

use XF\App;
use XF\Entity\Thread;
use XF\Service\AbstractService;
use XF\ThreadType\AbstractHandler;
use XFRM\Entity\ResourceItem;

class ChangeDiscussion extends AbstractService
{
	/**
	 * @var ResourceItem
	 */
	protected $resource;

	public function __construct(App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function disconnectDiscussion()
	{
		$this->resource->discussion_thread_id = 0;
		$this->resource->save();

		return true;
	}

	public function changeThreadByUrl($threadUrl, $checkPermissions = true, &$error = null)
	{
		$threadRepo = $this->repository('XF:Thread');
		$thread = $threadRepo->getThreadFromUrl($threadUrl, null, $threadFetchError);
		if (!$thread)
		{
			$error = $threadFetchError;
			return false;
		}

		return $this->changeThreadTo($thread, $checkPermissions, $error);
	}

	public function changeThreadTo(Thread $thread, $checkPermissions = true, &$error = null)
	{
		if ($checkPermissions && !$thread->canView($viewError))
		{
			$error = $viewError ?: \XF::phrase('do_not_have_permission');
			return false;
		}

		if ($thread->thread_id === $this->resource->discussion_thread_id)
		{
			return true;
		}

		if ($thread->discussion_type != AbstractHandler::BASIC_THREAD_TYPE)
		{
			$error = \XF::phrase('xfrm_new_resource_discussion_thread_must_be_standard_thread');
			return false;
		}

		$this->resource->discussion_thread_id = $thread->thread_id;
		$this->resource->save();

		return true;
	}

}
