<?php

namespace CloudCheats\ResourceCredits\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * @extends \XFRM\Pub\Controller\Category
 */
class Category extends XFCP_Category
{
	/**
	 * Handles credits price input when creating a new resource.
	 */
	public function actionAdd(ParameterBag $params): AbstractReply
	{
		$isPost      = $this->isPost();
		$creditPrice = $isPost ? $this->filter('cc_credits_price', 'uint') : null;

		$response = parent::actionAdd($params);

		// Save price after successful resource creation (redirect response = save succeeded)
		if ($isPost && $response instanceof \XF\Mvc\Reply\Redirect)
		{
			// Get the newly created resource ID from the last inserted row
			$resourceId = $this->db()->lastInsertId();

			if ($resourceId && $creditPrice > 0)
			{
				$this->db()->query(
					'UPDATE xf_rm_resource SET cc_credits_price = ? WHERE resource_id = ?',
					[$creditPrice, $resourceId]
				);
			}
		}

		return $response;
	}
}
