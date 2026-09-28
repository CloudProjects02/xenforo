<?php

namespace DBTech\Shop\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;

/**
 * Class Delete
 *
 * @package DBTech\Shop\ControllerPlugin
 */
class Delete extends AbstractPlugin
{
	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 * @param $stateKey
	 * @param $deleterService
	 * @param $contentType
	 * @param $deleteLink
	 * @param $editLink
	 * @param $redirectLink
	 * @param $title
	 * @param bool $canHardDelete
	 * @param bool $includeAuthorAlert
	 * @param null $templateName
	 * @param array $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionDeleteWithState(
		\XF\Mvc\Entity\Entity $entity,
		$stateKey,
		$deleterService,
		$contentType,
		$deleteLink,
		$editLink,
		$redirectLink,
		$title,
		$canHardDelete = false,
		$includeAuthorAlert = true,
		$templateName = null,
		$params = []
	) {
		if ($this->isPost())
		{
			$id = $entity->getIdentifierValues();
			if (!$id || count($id) != 1)
			{
				throw new \InvalidArgumentException("Entity does not have an ID or does not have a simple key");
			}
			$entityId = intval(reset($id));
			
			if ($entity->{$stateKey} == 'deleted')
			{
				$linkHash = $this->buildLinkHash($entityId);
				
				$type = $this->filter('hard_delete', 'uint');
				switch ($type)
				{
					case 0:
						return $this->redirect($redirectLink . $linkHash);

					case 1:
						$reason = $this->filter('reason', 'str');
						
						$deleter = $this->service($deleterService, $entity);
						if ($includeAuthorAlert && $this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}
						$deleter->delete('hard', $reason);
						
						/** @var \XF\ControllerPlugin\InlineMod $inlineModPlugin */
						$inlineModPlugin = $this->plugin('XF:InlineMod');
						$inlineModPlugin->clearIdFromCookie($contentType, $entityId);
						
						return $this->redirect($redirectLink);

					case 2:
						$deleter = $this->service($deleterService, $entity);
						if ($includeAuthorAlert && $this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}
						$deleter->unDelete();
						
						return $this->redirect($redirectLink . $linkHash);
				}
			}
			else
			{
				$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
				$reason = $this->filter('reason', 'str');
				
				$deleter = $this->service($deleterService, $entity);
				if ($includeAuthorAlert && $this->filter('author_alert', 'bool'))
				{
					$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
				}
				$deleter->delete($type, $reason);
				
				/** @var \XF\ControllerPlugin\InlineMod $inlineModPlugin */
				$inlineModPlugin = $this->plugin('XF:InlineMod');
				$inlineModPlugin->clearIdFromCookie($contentType, $entityId);
				
				return $this->redirect($redirectLink);
			}
		}
		
		$templateName = $templateName ?: 'public:dbtech_shop_delete_state';
		
		$viewParams = [
				'entity'             => $entity,
				'stateKey'           => $stateKey,
				'title'              => $title,
				'editLink'           => $editLink,
				'deleteLink'         => $deleteLink,
				'canHardDelete'      => $canHardDelete,
				'includeAuthorAlert' => $includeAuthorAlert
			] + $params;
		return $this->view('DBTech\Shop:Delete\State', $templateName, $viewParams);
	}
}