<?php

namespace XFRM\NewsFeed;

use XF\NewsFeed\AbstractHandler;
use XF\Repository\Attachment;

class ResourceItem extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['User', 'Description', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}

	protected function addAttachmentsToContent($content)
	{
		$descriptions = [];
		foreach ($content AS $resource)
		{
			$description = $resource->Description;
			if ($description)
			{
				$descriptions[$description->resource_update_id] = $description;
			}
		}

		/** @var Attachment $attachmentRepo */
		$attachmentRepo = \XF::repository('XF:Attachment');
		$attachmentRepo->addAttachmentsToContent($descriptions, 'resource_update');

		return $content;
	}
}
