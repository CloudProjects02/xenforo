<?php

namespace EAEAddons\ThreadCount\XF\Pub\Controller;

class Thread extends XFCP_Thread
{
	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$thread = parent::assertViewableThread($threadId, $extraWith);

		$minThreadCount = $visitor->hasNodePermission($thread->node_id, 'viewNodeThreadCount');
		if (!$minThreadCount)
		{
			return $thread;
		}
		
		if ($visitor->eaetc_thread_count < $minThreadCount)
		{
			throw $this->exception($this->noPermission());
		}
		return $thread;
	}
}