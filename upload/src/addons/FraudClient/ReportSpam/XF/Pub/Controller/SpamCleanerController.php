<?php

namespace FraudClient\ReportSpam\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Entity\User;
use XF\Util\Ip;

class SpamCleanerController extends XFCP_SpamCleanerController
{
	public function actionIndex(ParameterBag $params)
	{
		$response = parent::actionIndex($params);
		if ($this->isPost() && !$response instanceof \XF\Mvc\Reply\Error) {
			$user = $this->assertRecordExists(User::class, $params->user_id);
			$reportFraudClient = $this->filter('report_fraud_client', 'bool');
			if ($reportFraudClient) {

				if (!$user->canBeReportedFraudClient($error)) {
					return $this->noPermission($error);
				}

				$reportFraudClientPlugin = $this->plugin('FraudClient\ReportSpam:ReportFraudClient');
				$data['reason'] = 'spammer';
				$data['username'] = $user->username;
				$data['message'] = \XF::phrase('ms_fc_spam_cleaner_by_forum_staff');
				$data['email'] = $user->email;
				$data['ip'] = '';

				/** @var \XF\Repository\Ip $ipRepo */
				$ipRepo = $this->repository('XF:Ip');

				$ips = $ipRepo->getIpsByUser($user);
				if ($ips) {
					$ips = reset($ips);
					if (method_exists(Ip::class, 'binaryToString')) {
						// Newer XenForo version
						$data['ip'] = Ip::binaryToString($ips['ip']);
					} else {
						// Older XenForo version 2.2
						$data['ip'] = Ip::convertIpBinaryToString($ips['ip']);
					}
				}

				try {
					$reportFraudClientPlugin->sendReportToAPI($data);
				} catch (\Throwable $e) {
					// Spam Cleaner has already completed its core XenForo work. Log the
					// external failure without rolling back or masking that result.
					\XF::logException($e, false, 'FraudClient Spam Cleaner report error: ');
				}
			}
		}

		return $response;
	}
}
