<?php

namespace EAEAddons\ThreadCount\XF\Entity;

use XF\Mvc\Entity\Structure;

class Forum extends XFCP_Forum
{
	public function canCreateThread(&$error = null)
	{
		$parent = parent::canCreateThread($error);
		if (!$parent)
		{
			return false;
		}
		$visitor = \XF::visitor();

		$viewLimit = $visitor->hasNodePermission($this->node_id, 'createThreadCount');
		if (!$viewLimit)
		{
			return true;
		}
		return $visitor->eaetc_thread_count >= $viewLimit;
	}

	public function getNodeListExtras()
	{
		$visitor = \XF::visitor();

		$viewLimit = $visitor->hasNodePermission($this->node_id, 'viewOthersThreadCount');
		if (!$viewLimit)
		{
			return parent::getNodeListExtras();
		}

		if ($visitor->eaetc_thread_count >= $viewLimit)
		{
			return parent::getNodeListExtras();
		}
		else
		{
			return ['privateInfo' => true];
		}	
	}
}