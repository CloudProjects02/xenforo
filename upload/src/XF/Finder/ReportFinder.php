<?php

namespace XF\Finder;

use XF\Entity\Report;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

use function is_array;

/**
 * @method AbstractCollection<Report> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Report> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Report|null fetchOne(?int $offset = null)
 * @extends Finder<Report>
 */
class ReportFinder extends Finder
{
	public function isActive()
	{
		$this->where('report_state', ['open', 'assigned']);

		return $this;
	}

	public function inTimeFrame($timeFrame = null)
	{
		if ($timeFrame)
		{
			if (!is_array($timeFrame))
			{
				$timeFrom = $timeFrame;
				$timeTo = time();
			}
			else
			{
				$timeFrom = $timeFrame[0];
				$timeTo = $timeFrame[1];
			}

			$this->where(['last_modified_date', '>=', $timeFrom]);
			$this->where(['last_modified_date', '<=', $timeTo]);
		}

		return $this;
	}

	public function forContentUser($contentUser)
	{
		if (isset($contentUser['user_id']))
		{
			$userId = $contentUser['user_id'];
		}
		else
		{
			$userId = $contentUser;
		}
		$this->where('content_user_id', $userId);

		return $this;
	}
}
