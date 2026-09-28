<?php

namespace EAEAddons\ThreadCount\XF\Entity;

use XF\Mvc\Entity\Structure;

class Node extends XFCP_Node
{
	public function canView(&$error = null)
	{
		$parent = parent::canView($error);
		if (!$parent)
		{
			return false;
		}
		$visitor = \XF::visitor();

		$viewLimit = $visitor->hasNodePermission($this->node_id, 'viewNodeThreadCount');
		if (!$viewLimit)
		{
			return true;
		}
		return $visitor->eaetc_thread_count >= $viewLimit;
	}
}