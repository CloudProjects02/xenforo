<?php

namespace XenGenTr\XGTStyleV13\Widget;

class InfoBox extends \XF\Widget\AbstractWidget
{
	public function render()
	{
		return $this->renderer('xgtSv13_infobox');
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		return true;
	}
}
