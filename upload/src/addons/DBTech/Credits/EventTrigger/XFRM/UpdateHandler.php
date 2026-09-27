<?php

namespace DBTech\Credits\EventTrigger\XFRM;

use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\EventTrigger\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XFRM\Entity\ResourceUpdate;

class UpdateHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'canCancel' => true,
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
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($transaction->negate)
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_resourceupdate_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_resourceupdate_negate', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_resourceupdate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_resourceupdate', $transaction);
			}
		}
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
		/** @var ResourceUpdate $entity */

		$this->apply($entity->resource_update_id, [
			'content_type' => 'resource_update',
			'content_id' => $entity->resource_update_id,

			'timestamp' => $entity->post_date,
			'enableAlert' => false,
			'runPostSave' => false,
		], $entity->Resource->User);
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return ['Resource', 'Resource.User'];
	}
}