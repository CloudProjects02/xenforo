<?php

namespace EAEAddons\ThreadCount\XF\Searcher;

use XF\Mvc\Entity\Finder;

class User extends XFCP_User
{
	protected function applyCriteriaValue(Finder $finder, $key, $value, $column, $format, $relation)
	{
		if ($key == 'thread_count')
		{
			if (is_array($value))
			{
				$hasMin = isset($value['start']);
				$hasMax = isset($value['end']);

				$noBetween = false;
				if ($hasMin && $hasMax)
				{
					$noBetween = $value['start'] == $value['end'] ? true : false;
				}
				
				if ($hasMax)
				{
					$hasMax = $value['end'] > 0 ? true : false;
				}
				if ($noBetween)
				{
					$finder->where('eaetc_thread_count', '=', $value['start']);
					return;
				}
				else if ($hasMin && $hasMax)
				{
					$finder->where('eaetc_thread_count', 'BETWEEN', [$value['start'], $value['end']]);
					return;
				}
				else if ($hasMin)
				{
					$finder->where('eaetc_thread_count', '>=', $value['start']);
					return;
				}
				else if ($hasMax)
				{
					$finder->where('eaetc_thread_count', '<=', $value['end']);
					return;
				}
			}
			$finder->where('eaetc_thread_count', '>=', $value);
			return;
		}
		return parent::applyCriteriaValue($finder, $key, $value, $column, $format, $relation);
	}

	public function getFormDefaults()
	{
		$extraFields = ['thread_count' => ['end' => -1]];
		
		return parent::getFormDefaults() + $extraFields;
	}
}