<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Thread;

/**
 * @extends \XF\Service\Thread\MergerService
 */
class MergerService extends XFCP_MergerService
{
	protected function moveDataToTarget()
	{
		parent::moveDataToTarget();

		$db = $this->db();
		$target = $this->target;

		$sourceThreads = $this->sourceThreads;
		$sourceThreadIds = array_keys($sourceThreads);
		$sourceIdsQuoted = $db->quote($sourceThreadIds);

		$db->update(
			'xf_dbtech_credits_content_access_purchase',
			['content_id' => $target->thread_id],
			"content_type = 'thread' AND content_id IN ($sourceIdsQuoted)",
			[],
			'IGNORE'
		);
	}
}