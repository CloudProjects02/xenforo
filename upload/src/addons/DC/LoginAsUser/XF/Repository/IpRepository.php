<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;

/**
 * Every IP logging site in core funnels through this one method - there is exactly one
 * create(Ip::class) in the tree and it lives here - so a single override is total.
 *
 * null is core's own documented failure return, so a null no-op is inside the contract.
 */
class IpRepository extends XFCP_IpRepository
{
	public function logIp($userId, $ip, $contentType, $contentId, $action = '')
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_IP_LOGGING, (int) $userId))
		{
			return null;
		}

		return parent::logIp($userId, $ip, $contentType, $contentId, $action);
	}
}
