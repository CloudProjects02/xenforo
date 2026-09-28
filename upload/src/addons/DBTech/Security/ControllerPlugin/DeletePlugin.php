<?php

namespace DBTech\Security\ControllerPlugin;

use DBTech\Security\Admin\View\Delete\StateView;
use XF\ControllerPlugin\AbstractPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\Entity\Phrase;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Service\AbstractService;

class DeletePlugin extends AbstractPlugin
{
	/**
	 * @template T of AbstractService
	 * @param Entity $entity
	 * @param string $stateKey
	 * @param class-string<T> $deleterService
	 * @param string $contentType
	 * @param string $confirmUrl
	 * @param string $editLink
	 * @param string $redirectLink
	 * @param Phrase|string $title
	 * @param bool $canHardDelete
	 * @param bool $includeAuthorAlert
	 * @param string|null $templateName
	 * @param array $params
	 *
	 * @return AbstractReply
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function actionDeleteWithState(
		Entity $entity,
		string $stateKey,
		string $deleterService,
		string $contentType,
		string $confirmUrl,
		string $editLink,
		string $redirectLink,
		$title,
		bool $canHardDelete = false,
		bool $includeAuthorAlert = true,
		?string $templateName = null,
		array $params = []
	): AbstractReply
	{
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

						$deleter = \XF::app()->service($deleterService, $entity);
						if ($includeAuthorAlert && $this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}
						$deleter->delete('hard', $reason);

						$inlineModPlugin = $this->plugin(InlineModPlugin::class);
						$inlineModPlugin->clearIdFromCookie($contentType, $entityId);

						return $this->redirect($redirectLink);

					case 2:
						$deleter = \XF::app()->service($deleterService, $entity);
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

				$deleter = \XF::app()->service($deleterService, $entity);
				if ($includeAuthorAlert && $this->filter('author_alert', 'bool'))
				{
					$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
				}
				$deleter->delete($type, $reason);

				$inlineModPlugin = $this->plugin(InlineModPlugin::class);
				$inlineModPlugin->clearIdFromCookie($contentType, $entityId);

				return $this->redirect($redirectLink);
			}
		}

		$templateName = $templateName ?: 'public:dbtech_security_delete_state';

		/** @noinspection PhpFullyQualifiedNameUsageInspection */
		$viewClass = \XF::app()->get('app.classType') == 'Pub'
			? StateView::class
			: \DBTech\Security\Pub\View\Delete\StateView::class
		;

		$viewParams = [
			'entity'             => $entity,
			'stateKey'           => $stateKey,
			'title'              => $title,
			'editLink'           => $editLink,
			'confirmUrl'         => $confirmUrl,
			'canHardDelete'      => $canHardDelete,
			'includeAuthorAlert' => $includeAuthorAlert,
		] + $params;
		return $this->view($viewClass, $templateName, $viewParams);
	}
}