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
			// Extract resource_id from the redirect URL (e.g. /resources/slug.123/)
			$redirectUrl = $response->getUrl();
			if (preg_match('/\.(\d+)\/?$/', $redirectUrl, $m))
			{
				$resourceId = (int) $m[1];
			}
			else
			{
				$resourceId = (int) \XF::db()->lastInsertId();
			}

			if ($resourceId && $creditPrice > 0)
			{
				\XF::db()->query(
					'UPDATE xf_rm_resource SET cc_credits_price = ? WHERE resource_id = ?',
					[$creditPrice, $resourceId]
				);
			}
		}

		return $response;
	}
}
