<?php

namespace FraudClient\ReportSpam\XF\Pub\Controller;

use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class MemberController extends XFCP_MemberController
{
	public function actionReportFraudClient(ParameterBag $params)
	{
		$user = $this->assertViewableUser($params->user_id, [], true);
		if (!$user->canBeReportedFraudClient($error))
		{
			return $this->noPermission($error);
		}

		$reportFraudClientPlugin = $this->plugin('FraudClient\ReportSpam:ReportFraudClient');
		return $reportFraudClientPlugin->actionReport(
			'user', $user,
			$this->buildLink('members/report-fraud-client', $user),
			$this->buildLink('members', $user)
		);
	}
	public function actionCheckFraudClient(ParameterBag $params)
	{
		$user = $this->assertViewableUser($params->user_id, [], true);

		if (!$user->canCheckFraudClient($error))
		{
			return $this->noPermission($error);
		}

		if (!$this->isPost())
		{
			return $this->view(
				'FraudClient\ReportSpam:ReportFraudClient\CheckConfirm',
				'ms_fc_check_confirm',
				[
					'user' => $user,
					'confirmUrl' => $this->buildLink('members/check-fraud-client', $user)
				]
			);
		}

		$this->assertPostOnly();
		$reportFraudClientPlugin = $this->plugin('FraudClient\ReportSpam:ReportFraudClient');
		return $reportFraudClientPlugin->actionCheck($user);
	}
}
