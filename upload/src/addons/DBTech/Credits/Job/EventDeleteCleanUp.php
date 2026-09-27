<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Service\Event\DeleteCleanUpService;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class EventDeleteCleanUp extends AbstractJob
{
	/** @var array  */
	protected $defaultData = [
		'eventId' => null,
		'title' => null,

		'currentStep' => 0,
		'lastOffset' => null,

		'start' => 0,
	];

	/**
	 * @param int $maxRunTime
	 *
	 * @return JobResult
	 */
	public function run($maxRunTime): JobResult
	{
		$this->data['start']++;

		if (!$this->data['eventId'] || !$this->data['title'])
		{
			return $this->complete();
		}

		$deleter = \XF::app()->service(
			DeleteCleanUpService::class,
			$this->data['eventId'],
			$this->data['title']
		);
		$deleter->restoreState($this->data['currentStep'], $this->data['lastOffset']);

		$result = $deleter->cleanUp($maxRunTime);
		if ($result->isCompleted())
		{
			return $this->complete();
		}

		$continueData = $result->getContinueData();
		$this->data['currentStep'] = $continueData['currentStep'];
		$this->data['lastOffset'] = $continueData['lastOffset'];

		return $this->resume();
	}

	/**
	 * @return string
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('deleting');
		$typePhrase = $this->data['title'];
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return false;
	}
}