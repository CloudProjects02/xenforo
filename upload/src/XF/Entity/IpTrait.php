<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;
use XF\Util\Ip;

trait IpTrait
{
	public static function addIpStructureElements(Structure $structure)
	{
		$structure->relations['Ip'] = [
			'entity' => 'XF:Ip',
			'type' => self::TO_ONE,
			'conditions' => 'ip_id',
			'primary' => true,
		];

		$structure->getters['ip_address'] = true;
	}

	public function getIpAddress(): string
	{
		if ($this->Ip)
		{
			return Ip::binaryToString($this->Ip->ip, true, false);
		}

		return '';
	}
}
