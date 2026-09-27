<?php

namespace XenGenTr\XGTStyleV13\XF\Pub\Controller;

class MiscController extends XFCP_MiscController
{
	public function actionStyleVariation(): \XF\Mvc\Reply\AbstractReply
	{
		$visitor = \XF::visitor();

		if ($visitor->hasPermission('general', 'varyasyonKullanmaz') === false)
		{
			$reply = $this->notFound(\XF::phrase('requested_page_not_found'));
			$reply->httpCode = 410;
			return $reply;
		}

		return parent::actionStyleVariation();
	}
}
