<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;

class ErrorLogRepository extends Repository
{
	public function clearErrorLog()
	{
		$this->db()->emptyTable('xf_error_log');
	}

	public function hasErrorsInLog()
	{
		$hasErrors = $this->db()->fetchOne('
			SELECT error_id
			FROM xf_error_log
			LIMIT 1
		');

		return (bool) $hasErrors;
	}

	public function pruneErrorLogs(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$logLength = $this->options()->errorLogLength;
			if (!$logLength)
			{
				return 0;
			}

			$cutOff = \XF::$time - 86400 * $logLength;
		}

		return $this->db()->delete('xf_error_log', 'exception_date < ?', $cutOff);
	}
}
