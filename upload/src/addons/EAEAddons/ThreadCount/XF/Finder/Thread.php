<?php

namespace EAEAddons\ThreadCount\XF\Finder;

use XF\Mvc\Entity\Finder;

class Thread extends XFCP_Thread
{
	public function applyVisibilityChecksInForum(\XF\Entity\Forum $forum, $allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$parent = parent::applyVisibilityChecksInForum($forum, $allowOwnPending);

		$viewLimit = $visitor->hasNodePermission($forum->node_id, 'viewOthersThreadCount');
		if (!$viewLimit || !$visitor->hasNodePermission($forum->node_id, 'viewOthers'))
		{
			return $parent;
		}

		if ($visitor->eaetc_thread_count < $viewLimit)
		{
			if ($visitor->user_id)
			{
				$parent->where('user_id', $visitor->user_id);
			}
			else
			{
				$parent->whereSql('1=0');
			}
		}
		return $parent;
	}
}