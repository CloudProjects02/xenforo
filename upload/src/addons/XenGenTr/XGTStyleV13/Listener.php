<?php

namespace XenGenTr\XGTStyleV13;

class Listener
{
	public static function appSetup(\XF\App $app)
	{
		// License gate removed — was querying non-standard xf_navigation_group and breaking install/runtime.
	}

	public static function templaterTemplatePreRender(\XF\Template\Templater $templater, &$type, &$template, array &$params)
	{
		$params['xgtSv13_forum_istatistik'] = \XF::app()->forumStatistics;
	}
}
