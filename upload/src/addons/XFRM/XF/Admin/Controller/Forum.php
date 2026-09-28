<?php

namespace XFRM\XF\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Forum extends XFCP_Forum
{
	public function actionChangeType(ParameterBag $params)
	{
		$reply = parent::actionChangeType($params);

		if ($reply instanceof View)
		{
			$resourceCategory = \XF::em()->findOne('XFRM:Category', ['thread_node_id', $params->node_id]);
			if ($resourceCategory)
			{
				$reply->setParam('isResourceForum', true);
			}
		}

		return $reply;
	}
}
