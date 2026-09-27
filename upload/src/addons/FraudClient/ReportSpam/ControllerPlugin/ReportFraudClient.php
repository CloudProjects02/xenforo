<?php

namespace FraudClient\ReportSpam\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Entity;
use XF\Util\Ip;

class ReportFraudClient extends AbstractPlugin
{
	public function actionReport($contentType, Entity $content, $confirmUrl, $returnUrl, $options = [])
	{
		$options = array_merge([
			'view' => 'FraudClient\ReportSpam:ReportFraudClient\ReportFraudClient',
			'template' => 'ms_fc_report_create',
			'extraViewParams' => []
		], $options);

		$reportReasons = $this->getReportReasons();
		if ($this->request->isPost())
		{
			$message = trim($this->request->filter('message', 'str'));
			$reason = strtolower(trim($this->request->filter('reason', 'str')));

			if (strlen($message) < 20 || strlen($message) > 5000)
			{
				throw $this->exception($this->error(\XF::phrase('ms_fc_report_details_length')));
			}
			if (!array_key_exists($reason, $reportReasons))
			{
				throw $this->exception($this->error(\XF::phrase('please_select_valid_reason_for_reporting_this_user')));
			}

			$data = [
				'reason' => $reason,
				'username' => $content->username,
				'message' => $message,
				'email' => $content->email,
				'ip' => $this->getLatestIp($content)
			];

			$response = $this->sendReportToAPI($data);
			return $this->redirect($returnUrl, $response);
		}

		$viewParams = [
			'confirmUrl' => $confirmUrl,
			'content' => $content,
			'reportReasonPoints' => $reportReasons
		];
		return $this->view($options['view'], $options['template'], $viewParams + $options['extraViewParams']);
	}

	public function sendReportToAPI(array $data)
	{
		$apiKey = trim((string) \XF::options()->ms_fc_api_key);
		if ($apiKey === '')
		{
			throw $this->exception($this->error(\XF::phrase('ms_fc_api_key_missing')));
		}

		$params = [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $apiKey
			],
			'connect_timeout' => 5,
			'timeout' => 10,
			'http_errors' => false,
			'json' => [
				'reason' => $data['reason'],
				'details' => $data['message'],
				'clientEmail' => $data['email'],
				'clientUsername' => $data['username'],
				'clientIP' => $data['ip']
			]
		];

		try
		{
			$response = \XF::app()->http()->client()->post(
				'https://fraudclient.com/api.php?action=submit_report',
				$params
			);
			$body = json_decode((string) $response->getBody(), true);
			if (
				$response->getStatusCode() < 200
				|| $response->getStatusCode() >= 300
				|| !is_array($body)
				|| empty($body['message'])
			)
			{
				throw new \RuntimeException('FraudClient returned an invalid report response.');
			}
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, 'FraudClient API error: ');
			throw $this->exception($this->error(\XF::phrase('ms_fc_api_error_message')));
		}

		return (string) $body['message'];
	}

	public function actionCheck(Entity $content, $options = [])
	{
		$options = array_merge([
			'view' => 'FraudClient\ReportSpam:ReportFraudClient\Check',
			'template' => 'ms_fc_check_user_view',
			'extraViewParams' => []
		], $options);

		/** @var \FraudClient\ReportSpam\Repository\FraudClient $fraudRepo */
		$fraudRepo = $this->repository('FraudClient\ReportSpam:FraudClient');
		$results = $fraudRepo->checkUser($content);
		if (!empty($results['error']))
		{
			throw $this->exception($this->error($results['error']));
		}

		if (!empty($results['reports']))
		{
			foreach ($results['reports'] as &$report)
			{
				$timestamp = strtotime(($report['createdAt'] ?? '') . ' UTC');
				$report['createdAtTimestamp'] = $timestamp ?: 0;
			}
			unset($report);
		}

		$viewParams = [
			'user' => $content,
			'results' => $results,
			'hash' => $results['queryHash'] ?? ''
		];

		return $this->view($options['view'], $options['template'], $viewParams + $options['extraViewParams']);
	}

	protected function getReportReasons()
	{
		return [
			'fraud' => 'Fraud',
			'spammer' => 'Spammer',
			'staff abuse' => 'Staff Abuse',
			'early cancellation' => 'Early Cancellation',
			'chargeback' => 'Chargeback',
			'phishing' => 'Phishing',
			'copyright violation' => 'Copyright Violation',
			'other' => 'Other'
		];
	}

	protected function getLatestIp(Entity $content)
	{
		$ipRepo = $this->repository('XF:Ip');
		$ips = $ipRepo->getIpsByUser($content);
		if (!$ips)
		{
			return '';
		}

		$latestIp = reset($ips);
		return method_exists(Ip::class, 'binaryToString')
			? Ip::binaryToString($latestIp['ip'])
			: Ip::convertIpBinaryToString($latestIp['ip']);
	}
}
