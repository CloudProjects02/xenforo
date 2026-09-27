<?php

namespace XenGenTr\XGTStyleV13\XF\Template;

class Templater extends XFCP_Templater
{
	public function renderTemplate($template, array $params = [], $addDefaultParams = true, ?\XF\Template\ExtensionSet $extensionOverrides = null)
	{
		return parent::renderTemplate($template, $params, $addDefaultParams, $extensionOverrides);
	}
}
