<?php

namespace SynapseThemes\YearsOfService\XF\Entity;

use SynapseThemes\YearsOfService\Helper\ServiceHelper;

class User extends XFCP_User
{
	/**
	 * Get service badge data for this user
	 *
	 * @return array
	 */
	public function getServiceBadgeData()
	{
		return ServiceHelper::getServiceBadgeData($this);
	}

	/**
	 * Get the getter for serviceBadgeData
	 *
	 * @return array
	 */
	public function get($key)
	{
		if ($key === 'serviceBadgeData')
		{
			return $this->getServiceBadgeData();
		}

		return parent::get($key);
	}
}