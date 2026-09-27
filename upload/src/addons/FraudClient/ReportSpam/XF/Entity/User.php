<?php

namespace FraudClient\ReportSpam\XF\Entity;

class User extends XFCP_User
{
	public function canReportFraudClient(&$error = null)
	{
		if (
			!\XF::options()->ms_fc_enable
			|| !trim((string) \XF::options()->ms_fc_api_key)
			|| !$this->user_id
			|| !$this->hasPermission('general', 'reportFraudClient')
		)
		{
			$error = \XF::phraseDeferred('ms_fc_you_may_not_report_this_user_to_fraud_client');
			return false;
		}

		return true;
	}

	public function canBeReportedFraudClient(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->canReportFraudClient($error))
		{
			return false;
		}

		if ($visitor->user_id === $this->user_id || $this->is_admin || $this->is_moderator)
		{
			$error = \XF::phraseDeferred('ms_fc_you_may_not_report_this_user_to_fraud_client');
			return false;
		}

		return true;
	}

	public function canCheckFraudClient(&$error = null)
	{
		$visitor = \XF::visitor();
		if (
			!\XF::options()->ms_fc_enable
			|| !trim((string) \XF::options()->ms_fc_api_key)
			|| !$visitor->user_id
			|| !$visitor->hasPermission('general', 'checkFraudClient')
		)
		{
			$error = \XF::phraseDeferred('ms_fc_you_may_not_check_this_user_with_fraud_client');
			return false;
		}

		return true;
	}
}
