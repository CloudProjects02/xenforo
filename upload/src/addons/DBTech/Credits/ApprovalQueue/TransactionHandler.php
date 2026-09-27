<?php

namespace DBTech\Credits\ApprovalQueue;

use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\Service\Transaction\ApproveService;
use XF\ApprovalQueue\AbstractHandler;
use XF\Entity\ApprovalQueue;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class TransactionHandler extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canViewContent(Entity $content, &$error = null): bool
	{
		return true;
	}

	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null): bool
	{
		/** @var Transaction $content */
		return $content->canApproveUnapprove($error);
	}

	/**
	 * @param ApprovalQueue $unapprovedItem
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getTemplateData(ApprovalQueue $unapprovedItem): array
	{
		$data = parent::getTemplateData($unapprovedItem);

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
		$eventTrigger = $eventTriggerRepo->getHandler($unapprovedItem->Content->event_trigger_id);

		$data['eventTrigger'] = $eventTrigger;

		return $data;
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return [
			'Event',
			'Currency',
			'TargetUser',
			'SourceUser',
		];
	}

	/**
	 * @return array
	 */
	public function getDefaultActions(): array
	{
		return [
			'' => \XF::phrase('do_nothing'),
			'approve' => \XF::phrase('approve'),
			'reject' => \XF::phrase('reject'),
		];
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function actionApprove(Transaction $transaction): void
	{
		$notify = $this->getInput('notify', $transaction->transaction_id);

		$approver = \XF::app()->service(ApproveService::class, $transaction);
		$approver->setNotify($notify);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function actionReject(Transaction $transaction): void
	{
		$notify = $this->getInput('notify', $transaction->transaction_id);
		$reason = $this->getInput('reason', $transaction->transaction_id);

		$approver = \XF::app()->service(ApproveService::class, $transaction);
		$approver->setNotify($notify);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->setReason($reason);
		$approver->reject();
	}
}