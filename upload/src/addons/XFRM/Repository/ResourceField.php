<?php

namespace XFRM\Repository;

use XF\Repository\AbstractField;

class ResourceField extends AbstractField
{
	protected function getRegistryKey()
	{
		return 'xfrmResourceFields';
	}

	protected function getClassIdentifier()
	{
		return 'XFRM:ResourceField';
	}

	public function getDisplayGroups()
	{
		return [
			'above_info' => \XF::phrase('xfrm_above_resource_description'),
			'below_info' => \XF::phrase('xfrm_below_resource_description'),
			'above_rating' => \XF::phrase('xfrm_above_resource_rating'),
			'below_rating' => \XF::phrase('xfrm_below_resource_rating'),
			'below_sidebar_buttons' => \XF::phrase('xfrm_below_sidebar_buttons'),
			'extra_tab' => \XF::phrase('xfrm_extra_information_tab'),
			'new_tab' => \XF::phrase('xfrm_own_tab'),
		];
	}
}
