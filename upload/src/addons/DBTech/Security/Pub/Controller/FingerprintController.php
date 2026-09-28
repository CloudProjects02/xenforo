<?php

namespace DBTech\Security\Pub\Controller;

use DBTech\Security\Pub\View;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\XF\Entity\User;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class FingerprintController extends AbstractController
{
	/**
	 * @return AbstractReply
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionIndex(): AbstractReply
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$fingerprint = $this->filter('fingerprint', 'str');
			$components = $this->filter('components', 'array');

			$watcherRepo = \XF::app()->repository(WatcherRepository::class);
			$handler = $watcherRepo->getHandler($visitor->is_staff ? 'newstafffingerprint' : 'newfingerprint', false);

			if ($handler)
			{
				$handler->trigger([
					'fingerprint' => $fingerprint,
					'components' => $components,
					'ipaddress' => $this->request->getIp(),
				], $visitor);
			}
		}

		return $this->view(
			View\FingerprintView::class
		);
	}
}