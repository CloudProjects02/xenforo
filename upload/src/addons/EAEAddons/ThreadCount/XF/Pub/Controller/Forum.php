<?php

namespace EAEAddons\ThreadCount\XF\Pub\Controller;

class Forum extends XFCP_Forum
{
	protected function assertViewableForum($nodeIdOrName, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$forum = parent::assertViewableForum($nodeIdOrName, $extraWith);

		$minThreadCount = $visitor->hasNodePermission($forum->node_id, 'viewNodeThreadCount');
		if (!$minThreadCount)
		{
			return $forum;
		}
		
		if ($visitor->eaetc_thread_count < $minThreadCount)
		{
			throw $this->exception($this->noPermission());
		}
		return $forum;
	}
}