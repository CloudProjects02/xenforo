<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class TransactionRebuild extends AbstractJob
{
	/** @var array  */
	protected $defaultData = [
		'type' => null,
		'rebuild_types' => null,
		'start' => 0,
		'batch' => 500,
		'delay' => 0,
		'truncate' => false,
		'reset' => false,
	];
	protected string $builtType = '';
	protected int $builtLast = 0;


	/**
	 * @param int $maxRunTime
	 *
	 * @return JobResult
	 * @throws \Exception
	 */
	public function run($maxRunTime): JobResult
	{
		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		if (!is_array($this->data['rebuild_types']) && $this->data['truncate'])
		{
			\XF::app()->db()->emptyTable('xf_dbtech_credits_transaction');
			$this->data['truncate'] = false;

			if ($this->data['reset'])
			{
				\XF::app()->repository(CurrencyRepository::class)->resetCurrencies();

				$this->data['reset'] = false;
			}
		}

		if (!is_array($this->data['rebuild_types']))
		{
			$this->data['rebuild_types'] = $this->data['type']
				? [$this->data['type']]
				: $eventTriggerRepo->getRebuildableEventTriggers()
			;
			$this->data['type'] = null;
		}

		if (!$this->data['type'])
		{
			$this->data['type'] = array_shift($this->data['rebuild_types']);
			if (!$this->data['type'])
			{
				return $this->complete();
			}

			$this->data['start'] = 0;
		}

		$type = $this->data['type'];
		$start = $this->data['start'];

		$handler = $eventTriggerRepo->getHandler($type);

		if (!$handler->getOption('canRebuild'))
		{
			$this->data['type'] = null;
			return $this->resume();
		}

		$this->builtType = $this->data['type'];

		$last = $handler->rebuildRange($start, $this->data['batch']);
		if (!$last)
		{
			// done this type
			$this->data['type'] = null;
			return $this->resume();
		}

		$this->builtLast = $last;

		$this->data['start'] = $last;
		return $this->resume();
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('dbtech_credits_transactions');
		if ($this->builtType && $this->builtLast)
		{
			$handler = \XF::app()->repository(EventTriggerRepository::class)
				->getHandler($this->builtType)
			;

			return sprintf('%s... %s (%s %s)', $actionPhrase, $typePhrase, $handler->getTitle(), $this->builtLast);
		}
		else
		{
			return sprintf('%s... %s', $actionPhrase, $typePhrase);
		}
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return true;
	}
}