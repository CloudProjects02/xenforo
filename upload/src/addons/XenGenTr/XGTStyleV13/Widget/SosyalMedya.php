<?php

namespace XenGenTr\XGTStyleV13\Widget;

class SosyalMedya extends \XF\Widget\AbstractWidget
{
	public function render()
	{
		return $this->renderer('xgtSv13_sosyal_buton');
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		return true;
	}
}
