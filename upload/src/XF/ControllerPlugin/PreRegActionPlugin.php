<?php

namespace XF\ControllerPlugin;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\PreRegAction\AbstractHandler;
use XF\Repository\PreRegActionRepository;

/**
 * @method void assertNotFlooding($action, $floodingLimit = null)
 */
class PreRegActionPlugin extends AbstractPlugin
{
	public function actionPreRegAction($actionType, Entity $containerContent, array $actionData)
	{
		if (!\XF::visitor()->canTriggerPreRegAction())
		{
			return $this->noPermission();
		}

		$this->assertNotFlooding('post');

		$preRegActionRepo = $this->getPreRegActionRepo();

		/** @var AbstractHandler $handler */
		$handler = $preRegActionRepo->getActionHandler($actionType);
		$action = $handler->saveAction($containerContent, $actionData);

		$preRegActionRepo->limitPreRegActionsByIp($action->ip_address, 5);

		$session = $this->controller->session();

		$existingActionKey = $session->preRegActionKey;
		if ($existingActionKey)
		{
			$preRegActionRepo->deleteActionByKey($existingActionKey);
		}

		$session->preRegActionKey = $action->guest_key;

		return $this->redirect($this->buildLink('register'));
	}

	/**
	 * @return Repository|PreRegActionRepository
	 */
	protected function getPreRegActionRepo()
	{
		return $this->repository(PreRegActionRepository::class);
	}
}
