<?php

namespace XenSupport\Staff\Widget;

use XF\Widget\AbstractWidget;
use XenSupport\Staff\Repository\Staff as StaffRepo;

class Staff extends AbstractWidget
{
	protected $defaultOptions = [
		'style' => 'grid',
		'limit' => 6,
	];

	public function render()
	{
		/** @var StaffRepo $repo */
		$repo = $this->repository('XenSupport\Staff:Staff');

		$limit = (int) ($this->options['limit'] ?? 6);
		if ($limit < 1) { $limit = 6; }

		$data = $repo->findStaffMembers($limit);

		// Flatten groups into a single list (preserves priority sorting)
		$staff = [];
		foreach ($data['groups'] AS $group)
		{
			foreach ($group['users'] AS $entry)
			{
				$staff[] = $entry;
				if (count($staff) >= $limit) break 2;
			}
		}

		$style = $this->options['style'] ?? 'grid';
		if (!in_array($style, ['grid', 'compact', 'spotlight'], true))
		{
			$style = 'grid';
		}

		$featured = null;
		if ($style === 'spotlight' && $staff)
		{
			// Rotate based on day of year so it changes daily but stable per page load
			$idx = (int) date('z') % count($staff);
			$featured = $staff[$idx];
		}

		return $this->renderer('widget_xs_staff', [
			'staff' => $staff,
			'style' => $style,
			'featured' => $featured,
		]);
	}

	public function getOptionsTemplate()
	{
		return 'widget_options_xs_staff';
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'style' => 'str',
			'limit' => 'uint',
		]);
		if (!in_array($options['style'], ['grid', 'compact', 'spotlight'], true))
		{
			$options['style'] = 'grid';
		}
		if ($options['limit'] < 1) $options['limit'] = 6;
		if ($options['limit'] > 30) $options['limit'] = 30;
		return true;
	}
}
