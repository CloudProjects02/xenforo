<?php

namespace NF\Tickets\XFRM\Pub\Controller;

use NF\Tickets\Repository\Category;
use NF\Tickets\XF\Entity\User;
use SV\StandardLib\Helper;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

/**
 * @extends \XFRM\Pub\Controller\ResourceItem
 */
class ResourceItem extends XFCP_ResourceItem
{
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$reply = parent::actionEdit($params);

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($reply instanceof View && $visitor->canAccessTicketQueue())
		{
			$ticketCategoryRepo = Helper::repository(Category::class);
			$reply->setParam('nfTicketCategories', $ticketCategoryRepo->getCategoryOptionsData());
		}

		return $reply;
	}

	protected function setupResourceEdit(\XFRM\Entity\ResourceItem $resource)
	{
		$editor = parent::setupResourceEdit($resource);

		/** @var User $visitor */
		$visitor = \XF::visitor();
		if ($visitor->canAccessTicketQueue())
		{
			$editor->getResource()->nf_tickets_category_id = $this->filter('nf_tickets_category_id', 'uint');
		}

		return $editor;
	}
}
