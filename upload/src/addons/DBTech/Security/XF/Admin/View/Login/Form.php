<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Admin\View\Login;

class Form extends XFCP_Form
{
	public function renderHtml()
	{
		if (\XF::options()->dbtechSecurityLoginCaptcha['admin'])
		{
			$this->params['captcha'] = true;
		}
	}
}