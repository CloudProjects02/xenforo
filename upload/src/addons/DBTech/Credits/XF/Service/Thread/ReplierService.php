<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Thread;

/**
 * @extends \XF\Service\Thread\ReplierService
 */
class ReplierService extends XFCP_ReplierService
{
	/**
	 *
	 */
	public function setIsAutomated()
	{
		parent::setIsAutomated();

		$this->postPreparer->setApplyDragonByteCreditsEvents(false);
	}

	/**
	 * @return array
	 */
	protected function _validate()
	{
		$errors = parent::_validate();

		$creditsErrors = $this->postPreparer->validateDragonByteCreditsEventsBeforeInsert();
		return array_merge($errors, $creditsErrors);
	}
}