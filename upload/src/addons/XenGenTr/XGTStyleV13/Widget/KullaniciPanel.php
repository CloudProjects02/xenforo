<?php

namespace XenGenTr\XGTStyleV12\Widget;

use XF\Widget\AbstractWidget;

class KullaniciPanel extends AbstractWidget
{
	public function render()
	{
		return $this->renderer('xgtSv12_kullanici_panel');
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		return true;
	}
}