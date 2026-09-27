<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Post;

/**
 * @extends \XF\Service\Post\EditorService
 */
class EditorService extends XFCP_EditorService
{
	/**
	 * @return array
	 */
	protected function _validate()
	{
		$errors = parent::_validate();

		$creditsErrors = $this->postPreparer->validateDragonByteCreditsEventsBeforeUpdate();
		return array_merge($errors, $creditsErrors);
	}
}