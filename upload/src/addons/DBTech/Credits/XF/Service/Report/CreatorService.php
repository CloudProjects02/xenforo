<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Report;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Report;
use XF\PrintableException;

/**
 * @extends \XF\Service\Report\CreatorService
 */
class CreatorService extends XFCP_CreatorService
{
	/**
	 * @return array
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _validate()
	{
		$previous = parent::_validate();

		if (empty($previous) && !$this->threadCreator)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$eventTriggerRepo->getHandler('report')
				->testApply([], $this->user)
			;

			$eventTriggerRepo->getHandler('reported')
				->testApply([], $this->report->User)
			;
		}

		return $previous;
	}

	/**
	 * @return Report
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _save()
	{
		$report = parent::_save();

		if (!$this->threadCreator)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$eventTriggerRepo->getHandler('report')
				->apply($report->report_id, [
					'content_type' => $report->content_type,
					'content_id' => $report->content_id,
				], $this->user)
			;

			$eventTriggerRepo->getHandler('reported')
				->apply($report->report_id, [
					'content_type' => $report->content_type,
					'content_id' => $report->content_id,
				], $this->report->User)
			;
		}

		return $report;
	}
}