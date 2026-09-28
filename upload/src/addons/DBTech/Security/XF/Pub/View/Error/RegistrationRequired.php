<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Pub\View\Error;

class RegistrationRequired extends XFCP_RegistrationRequired
{
	public function renderHtml()
	{
		if (is_callable(parent::class . '::renderHtml'))
		{
			/** @noinspection PhpUndefinedMethodInspection */
			parent::renderHtml();
		}

		if (\XF::options()->dbtechSecurityLoginCaptcha['public'])
		{
			$this->params['captcha'] = true;
		}
	}

	public function renderJson()
	{
		if (is_callable(parent::class . '::renderHtml'))
		{
			/** @noinspection PhpUndefinedMethodInspection */
			parent::renderJson();
		}

		if (\XF::options()->dbtechSecurityLoginCaptcha['public'])
		{
			$this->params['captcha'] = true;
		}
	}
}