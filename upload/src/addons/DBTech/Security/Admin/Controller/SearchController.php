<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User;
use XF\Finder\IpFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\UserRepository;
use XF\Util\Ip;

class SearchController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\Search\IndexView::class,
			'dbtech_security_ip_search'
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIp(): AbstractReply
	{
		$db = \XF::app()->db();

		$user = null;
		$username = $this->filter('username', 'str');
		if ($username)
		{
			$user = \XF::app()->repository(UserRepository::class)->getUserByNameOrEmail($username);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}

		$ipAddress = $this->filter('ipaddress', 'str');

		$where = '';
		if ($user)
		{
			$where .= ' AND user_id = ' . $db->quote($user->user_id);
		}
		if ($ipAddress)
		{
			$where .= ' AND ip = ' . $db->quote(Ip::stringToBinary($ipAddress));
		}

		if (empty($where))
		{
			return $this->error(\XF::phrase('dbtech_security_please_enter_user_or_ip'));
		}

		// written this way due to mysql's ridiculous sub-query performance
		/** @noinspection SqlConstantExpression */
		$recentIps = $db->fetchAllColumn("
			SELECT DISTINCT ip
			FROM xf_ip
			WHERE 1=1 $where
			LIMIT 500
		");
		if (!$recentIps)
		{
			return $this->view(
				View\Search\IpView::class,
				'dbtech_security_ip_search_ip'
			);
		}

		$ipLogs = $db->fetchAll('
			SELECT user_id,
				ip,
				MIN(log_date) AS first_date,
				MAX(log_date) AS last_date,
				COUNT(*) AS total
			FROM xf_ip
			WHERE ip IN (' . $db->quote($recentIps) . ')
				AND user_id > 0
				' . ($user ? "AND user_id = " . $user->user_id : '') . '
			GROUP BY user_id, ip
			LIMIT 1000
		');

		$userIpLogs = [];
		foreach ($ipLogs AS $ipLog)
		{
			$userIpLogs[$ipLog['user_id']][$ipLog['ip']] = [
				'ip' => $ipLog['ip'],
				'first_date' => $ipLog['first_date'],
				'last_date' => $ipLog['last_date'],
				'total' => $ipLog['total'],
			];
		}

		if (!$userIpLogs)
		{
			return $this->view(
				View\Search\IpView::class,
				'dbtech_security_ip_search_ip'
			);
		}

		$users = \XF::app()->em()->findByIds(User::class, array_keys($userIpLogs));
		$output = [];

		foreach ($users AS $user)
		{
			$output[$user->user_id] = [
				'user_id' => $user->user_id,
				'user' => $user,
				'ips' => $userIpLogs[$user->user_id],
			];
		}
		$viewParams = [
			'users' => $output,
		];
		return $this->view(
			View\Search\IpView::class,
			'dbtech_security_ip_search_ip',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionNewIps(): AbstractReply
	{
		$db = \XF::app()->db();

		$cutOff = $this->filter('firstvisit', 'datetime');

		$ipLogs = $db->fetchAll('
			SELECT user_id,
				ip,
				MIN(log_date) AS first_date,
				MAX(log_date) AS last_date,
				COUNT(*) AS total
			FROM xf_ip
			WHERE user_id > 0
			GROUP BY user_id, ip
			HAVING first_date > ?
			LIMIT 1000
		', [$cutOff]);

		$userIpLogs = [];
		foreach ($ipLogs AS $ipLog)
		{
			$userIpLogs[$ipLog['user_id']][$ipLog['ip']] = [
				'ip' => $ipLog['ip'],
				'first_date' => $ipLog['first_date'],
				'last_date' => $ipLog['last_date'],
				'total' => $ipLog['total'],
			];
		}

		if (!$userIpLogs)
		{
			return $this->view(
				View\Search\NewIpView::class,
				'dbtech_security_ip_search_new'
			);
		}

		$users = \XF::app()->em()->findByIds(User::class, array_keys($userIpLogs));
		$output = [];

		foreach ($users AS $user)
		{
			$output[$user->user_id] = [
				'user_id' => $user->user_id,
				'user' => $user,
				'ips' => $userIpLogs[$user->user_id],
			];
		}
		$viewParams = [
			'users' => $output,
		];
		return $this->view(
			View\Search\NewIpView::class,
			'dbtech_security_ip_search_new',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionDuplicateIps(): AbstractReply
	{
		$db = \XF::app()->db();

		$cutOff = \XF::$time - \XF::app()->options()->sharedIpsCheckLimit * 86400;

		// written this way due to mysql's ridiculous sub-query performance
		$recentIps = $db->fetchAllColumn("
			SELECT DISTINCT ip
			FROM xf_ip
			WHERE log_date > ?
			LIMIT 500
		", [$cutOff]);
		if (!$recentIps)
		{
			return $this->view(
				View\Search\DuplicateView::class,
				'dbtech_security_ip_search_duplicate'
			);
		}

		$ipLogs = $db->fetchAll('
			SELECT user_id,
				ip,
				MIN(log_date) AS first_date,
				MAX(log_date) AS last_date,
				COUNT(*) AS total
			FROM xf_ip
			WHERE ip IN (' . $db->quote($recentIps) . ')
				AND user_id > 0
				AND log_date > ?
			GROUP BY user_id, ip
			LIMIT 1000
		', [$cutOff]);

		$userIpLogs = [];
		foreach ($ipLogs AS $ipLog)
		{
			$ip = Ip::binaryToString($ipLog['ip']);

			$userIpLogs[$ip][$ipLog['user_id']] = [
				'ip' => $ipLog['ip'],
				'first_date' => $ipLog['first_date'],
				'last_date' => $ipLog['last_date'],
				'total' => $ipLog['total'],
			];
		}

		$userIds = [];
		foreach ($userIpLogs AS $ip => $users)
		{
			if (count($users) == 1)
			{
				unset($userIpLogs[$ip]);
			}

			$userIds = array_merge($userIds, array_keys($users));
		}

		if (!$userIpLogs)
		{
			return $this->view(
				View\Search\DuplicateView::class,
				'dbtech_security_ip_search_duplicate'
			);
		}

		$users = \XF::app()->em()->findByIds(User::class, array_unique($userIds));

		$viewParams = [
			'ipList' => $userIpLogs,
			'users' => $users,
		];
		return $this->view(
			View\Search\DuplicateView::class,
			'dbtech_security_ip_search_duplicate',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionSuspectIps(): AbstractReply
	{
		$ipList = [];

		$ips = \XF::app()->db()->fetchAll("
			(SELECT ipaddress FROM xf_dbtech_security_admin_strike)
			UNION DISTINCT
			(SELECT ipaddress FROM xf_dbtech_security_compromised_log)
			UNION DISTINCT
			(SELECT ip FROM xf_ip_match WHERE match_type = 'banned' AND dbtech_security_comment = '')
			UNION DISTINCT
			(SELECT ipaddress FROM xf_dbtech_security_login_strike)
			UNION DISTINCT
			(SELECT ip_address FROM xf_dbtech_security_watcher_log WHERE ip_address <> '')
		");
		foreach ($ips AS $ip)
		{
			if ($ip['ipaddress'] == '::1' or $ip['ipaddress'] == '127.0.0.1')
			{
				// Skip these
				continue;
			}

			if (str_contains($ip['ipaddress'], ':'))
			{
				// IPv6
				$parts = explode(':', $ip['ipaddress']);
				$partialIp = $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':' . $parts[3] . ':0000:0000:0000:0000/64';
			}
			else
			{
				// IPv4
				$parts = explode('.', $ip['ipaddress']);
				$partialIp = $parts[0] . '.' . $parts[1] . '.0.0/16';
			}

			if (!isset($ipList[$partialIp]))
			{
				// Begin at 0 matches
				$ipList[$partialIp] = 0;
			}

			// This partial range had a hit
			$ipList[$partialIp]++;
		}

		// Sort by value in descending order
		arsort($ipList);

		$viewParams = [
			'ipList' => $ipList,
		];
		return $this->view(
			View\Search\SuspectView::class,
			'dbtech_security_ip_search_suspect',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIntruderIps(): AbstractReply
	{
		$ipList = [];

		$ips = \XF::app()->db()->fetchAll('
			SELECT ipaddress, username, COUNT(*) AS strikes
			FROM xf_dbtech_security_login_strike
			WHERE ipaddress <> \'\'
				AND valid_user = 1
			GROUP BY ipaddress, username
			HAVING strikes > 1
			ORDER BY strikes DESC
		');
		foreach ($ips AS $ip)
		{
			if (!isset($ipList[$ip['ipaddress']]))
			{
				$ipList[$ip['ipaddress']] = [
					'failed' => [],
					'successful' => [],
				];
			}

			$ipList[$ip['ipaddress']]['failed'][] = [
				'username' => $ip['username'],
				'count' => $ip['strikes'],
			];
		}

		if (empty($ipList))
		{
			return $this->view(
				View\Search\IntruderView::class,
				'dbtech_security_ip_search_intruder'
			);
		}

		$binaryIps = [];
		foreach (array_keys($ipList) AS $ip)
		{
			$binaryIps[] = Ip::stringToBinary($ip);
		}

		$ips = \XF::app()->finder(IpFinder::class)
			->with('User', true)
			->where('ip', $binaryIps)
			->order('log_date', 'DESC')
			->fetch()
			->groupBy('ip', function (\XF\Entity\Ip $ip): string
			{
				return $ip->User->username;
			});

		foreach ($ips AS $ipBinary => $users)
		{
			$ip = Ip::binaryToString($ipBinary);

			if (!isset($ipList[$ip]))
			{
				$ipList[$ip] = [
					'failed' => [],
					'successful' => [],
				];
			}

			/** @var \XF\Entity\Ip $ipUser */
			foreach ($users AS $ipUser)
			{
				$ipList[$ip]['successful'][] = [
					'username' => $ipUser->User->username,
					'user' => $ipUser->User,
					'count'    => 1,
				];
			}
		}

		$viewParams = [
			'ipList' => $ipList,
		];
		return $this->view(
			View\Search\IntruderView::class,
			'dbtech_security_ip_search_intruder',
			$viewParams
		);
	}
}