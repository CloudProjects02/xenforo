<?php

namespace DBTech\Credits\Alert;

use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

class TransactionHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Currency', 'Event', 'TargetUser', 'SourceUser'];
	}

	/**
	 * @param $action
	 *
	 * @return string
	 */
	public function getTemplateName($action): string
	{
		return match ($action)
		{
			'adjust', 'donate' => parent::getTemplateName($action),
			default => 'public:alert_' . $this->contentType,
		};
	}

	/**
	 * @param $action
	 *
	 * @return string
	 */
	public function getPushTemplateName($action): string
	{
		return match ($action)
		{
			'adjust', 'donate' => parent::getPushTemplateName($action),
			default => 'public:push_' . $this->contentType,
		};
	}

	/**
	 * @param $action
	 * @param UserAlert $alert
	 * @param Entity|null $content
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getTemplateData($action, UserAlert $alert, ?Entity $content = null): array
	{
		/** @var Transaction $content */
		$item = parent::getTemplateData($action, $alert, $content);

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		switch ($action)
		{
			case 'adjust':
			case 'donate':
				$item['amount'] = abs($content->amount);
				break;

			default:
				$item['phrase'] = $eventTriggerRepo->getHandler($content->event_trigger_id)
					->alertTemplate($content);
				break;
		}

		return $item;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getOptOutActions(): array
	{
		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		return $eventTriggerRepo
			->getEventTriggers(true, true)
			->pluck(function (\DBTech\Credits\EventTrigger\AbstractHandler $e, $k): array
			{
				return [$k, $e->getContentType()];
			}, false)
		;
	}

	/**
	 * The display order of this type's alert opt outs.
	 *
	 * @return int
	 */
	public function getOptOutDisplayOrder(): int
	{
		return 90000;
	}
}