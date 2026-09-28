<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use XF\Diff;

/**
 * @extends \XF\Entity\Template
 */
class Template extends XFCP_Template
{
	protected function updateTemplateHistoryLog()
	{
		parent::updateTemplateHistoryLog();
		$options = \XF::options();

		if (!$options->dbtech_security_templatehealth)
		{
			// We're not doing this
			return;
		}

		if ($this->isUpdate()
			&& $this->isChanged('template')
			&& $this->title !== 'dbtech_security_alert_template'
			&& $this->style_id > 0
		)
		{
			$diff = new Diff();
			$diffs = $diff->findDifferences($this->getExistingValue('template'), $this->template);

			// Create appropriate mail object
			\XF::app()->mailer()->newMail()
				->setTemplate('dbtech_security_alert_template', [
					'type' => \XF::phrase('dbtech_security_watcher_templatemod'),
					'diffs' => $diffs,
					'templateObj' => $this,
				])
				->setTo($options->contactEmailAddress)
				->queue();
		}
	}
}