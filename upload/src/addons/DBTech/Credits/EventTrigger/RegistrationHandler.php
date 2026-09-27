<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class RegistrationHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canCharge' => false,
			'useUserGroups' => false,
			'canRebuild' => true,
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_registration', $transaction);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}

	/**
	 * @param Entity $entity
	 *
	 * @throws PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var User $entity */

		$this->apply($entity->user_id, [
			'source_user_id' => $entity->user_id,

			'content_type' => 'user',
			'content_id' => $entity->user_id,

			'timestamp' => $entity->register_date,
			'enableAlert' => false,
			'runPostSave' => false,
		], $entity);
	}
}