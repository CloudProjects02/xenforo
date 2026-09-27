<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\Report;

class ReportHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return Report::class;
	}
}
