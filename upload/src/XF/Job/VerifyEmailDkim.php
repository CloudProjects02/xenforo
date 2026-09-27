<?php

namespace XF\Job;

use XF\Repository\EmailDkimRepository;
use XF\Repository\OptionRepository;

class VerifyEmailDkim extends AbstractJob
{
	use Retryable;

	public function run($maxRunTime): JobResult
	{
		$optionValue = \XF::options()->emailDkim;

		if (!$optionValue || $optionValue['verified'] == true)
		{
			return $this->complete();
		}

		$optionRepo = $this->app->repository(OptionRepository::class);

		$emailDkimRepo = $this->app->repository(EmailDkimRepository::class);
		$verified = $emailDkimRepo->verifyDnsRecordForDomain($optionValue['domain']);

		if (!$verified)
		{
			$nextAttemptOrComplete = $this->attemptLaterOrComplete();

			if ($nextAttemptOrComplete->result === JobResult::RESULT_COMPLETED)
			{
				// officially give up, something is wonky
				$optionValue['failed'] = true;
				$optionRepo->updateOption('emailDkim', $optionValue);
			}

			return $nextAttemptOrComplete;
		}

		$optionValue['verified'] = true;
		$optionRepo->updateOption('emailDkim', $optionValue);

		return $this->complete();
	}

	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('verifying_dns_records_for_email_dkim');
		return sprintf('%s...', $actionPhrase);
	}

	public function canCancel(): bool
	{
		return false;
	}

	public function canTriggerByChoice(): bool
	{
		return false;
	}

	protected function calculateNextAttemptDate(int $previousAttempts): ?int
	{
		switch ($previousAttempts)
		{
			case 0: $delay = 5 * 60; break; // 5 minutes
			case 1: $delay = 30 * 60; break; // 30 minutes
			case 2: $delay = 3600; break; // 1 hour
			case 3: $delay = 12 * 60 * 60; break; // 12 hours
			case 4: $delay = 24 * 60 * 60; break; // 24 hours
			default: return null; // give up
		}

		return time() + $delay;
	}
}
