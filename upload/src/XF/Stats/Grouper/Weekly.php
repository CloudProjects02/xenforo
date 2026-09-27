<?php

namespace XF\Stats\Grouper;

class Weekly extends AbstractGrouper
{
	public function getGrouping($timestamp)
	{
		return gmdate('o-W', $timestamp);
	}

	public function getLabel($groupValue, $timestamp)
	{
		return $this->language->date($timestamp, 'W o');
	}

	public function getDefaultStartDate()
	{
		return strtotime('-1 year');
	}
}
