<?php

namespace DBTech\Security\Repository;

use XF\Mvc\Entity\Repository;

class LogRepository extends Repository
{
	/**
	 *
	 */
	public function prune(): void
	{
		$options = $this->options();
		$db = $this->db();

		if ($options->dbtech_security_adminstrikes_prune)
		{
			$db->delete(
				'xf_dbtech_security_admin_strike',
				'dateline <= ?',
				(\XF::$time - (86400 * $options->dbtech_security_adminstrikes_prune))
			);
		}

		if ($options->dbtech_security_loginstrikes_prune)
		{
			$db->delete(
				'xf_dbtech_security_login_strike',
				'dateline <= ' . (\XF::$time - (86400 * $options->dbtech_security_loginstrikes_prune))
			);
		}

		if ($options->dbtech_security_ipverify_prune)
		{
			$db->delete(
				'xf_dbtech_security_ip_verify',
				'dateline <= ' . (\XF::$time - (86400 * $options->dbtech_security_ipverify_prune))
			);
		}
	}
}