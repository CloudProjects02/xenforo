<?php

namespace CloudCheats\ResourceCredits\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * @extends \XFRM\Pub\Controller\ResourceItem
 */
class ResourceItem extends XFCP_ResourceItem
{
	/**
	 * Injects cc_has_purchased and cc_credits_price into the resource view params.
	 */
	public function actionView(ParameterBag $params): AbstractReply
	{
		$response = parent::actionView($params);

		if ($response instanceof \XF\Mvc\Reply\View)
		{
			$resource = $response->getParam('resource');
			$price    = $resource ? (float) ($resource->cc_credits_price ?? 0) : 0.0;

			if ($price > 0)
			{
				$visitor   = \XF::visitor();
				$purchased = $visitor->user_id
					? $this->repository('CloudCheats\ResourceCredits:ResourcePurchase')
						->hasPurchased($resource->resource_id, $visitor->user_id)
					: false;
			}
			else
			{
				$purchased = true;
			}

			$response->setParam('cc_credits_price', $price);
			$response->setParam('cc_has_purchased', $purchased);
		}

		return $response;
	}

	/**
	 * Intercepts the download action.
	 * If resource has a credits price and user hasn't paid, redirect to purchase page.
	 */
	public function actionDownload(ParameterBag $params): AbstractReply
	{
		$resource = $this->assertViewableResource($params->resource_id);

		$price = (float) ($resource->cc_credits_price ?? 0);

		if ($price > 0)
		{
			$visitor = \XF::visitor();

			if (!$visitor->user_id)
			{
				return $this->redirect(
					$this->buildLink('login', null, ['redirect' => $this->request->getFullRequestUri()])
				);
			}

			/** @var \CloudCheats\ResourceCredits\Repository\ResourcePurchase $repo */
			$repo = $this->repository('CloudCheats\ResourceCredits:ResourcePurchase');

			if (!$repo->hasPurchased($resource->resource_id, $visitor->user_id))
			{
				return $this->redirect(
					$this->buildLink('resources/cc-purchase', $resource)
				);
			}
		}

		return parent::actionDownload($params);
	}

	/**
	 * Handles the credits purchase confirmation page.
	 * GET  → shows price info + confirm button
	 * POST → deducts credits, records purchase, redirects to download
	 */
	public function actionCcPurchase(ParameterBag $params): AbstractReply
	{
		$resource = $this->assertViewableResource($params->resource_id);
		$price    = (float) ($resource->cc_credits_price ?? 0);

		if ($price <= 0)
		{
			return $this->redirect($this->buildLink('resources', $resource));
		}

		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return $this->redirect($this->buildLink('login'));
		}

		/** @var \CloudCheats\ResourceCredits\Repository\ResourcePurchase $repo */
		$repo = $this->repository('CloudCheats\ResourceCredits:ResourcePurchase');

		// Already purchased — go straight to download
		if ($repo->hasPurchased($resource->resource_id, $visitor->user_id))
		{
			return $this->redirect($this->buildLink('resources/download', $resource));
		}

		$currency = $repo->getCurrency();
		if (!$currency)
		{
			return $this->error('No active credits currency found.');
		}

		if ($this->isPost())
		{
			$this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

			$success = $repo->deductCredits($visitor, $currency, $price, $resource->resource_id);

			if (!$success)
			{
				$balance = $repo->getUserBalance($visitor, $currency);
				return $this->error(
					\XF::phrase('cc_rc_insufficient_funds', [
						'price'    => number_format($price),
						'balance'  => number_format($balance),
						'currency' => $currency['title'],
					])
				);
			}

			$repo->recordPurchase($resource->resource_id, $visitor->user_id, $price);

			return $this->redirect($this->buildLink('resources/download', $resource));
		}

		$balance = $repo->getUserBalance($visitor, $currency);

		return $this->view(
			'CloudCheats\ResourceCredits:Purchase',
			'cc_resource_credits_purchase',
			[
				'resource'   => $resource,
				'price'      => $price,
				'balance'    => $balance,
				'currency'   => $currency,
				'canAfford'  => $balance >= $price,
			]
		);
	}

	/**
	 * Handles credits price input when editing an existing resource.
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$isPost = $this->isPost();
		$creditPrice = $isPost ? $this->filter('cc_credits_price', 'uint') : null;

		$response = parent::actionEdit($params);

		// Save price after successful resource save (redirect response = save succeeded)
		if ($isPost && $response instanceof \XF\Mvc\Reply\Redirect)
		{
			$this->db()->query(
				'UPDATE xf_rm_resource SET cc_credits_price = ? WHERE resource_id = ?',
				[$creditPrice > 0 ? $creditPrice : null, $params->resource_id]
			);
		}

		return $response;
	}
}
