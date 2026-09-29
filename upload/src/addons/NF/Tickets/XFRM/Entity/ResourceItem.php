<?php

namespace NF\Tickets\XFRM\Entity;

use NF\Tickets\Entity\Category as TicketCategory;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XFRM\Entity\ResourceItem
 * @property int|null            $nf_tickets_category_id
 * @property-read TicketCategory $TicketCategory
 * @property-read Category       $Category
 */
class ResourceItem extends XFCP_ResourceItem
{
	public function hasLinkedTicketCategory(): bool
	{
		return ($this->nf_tickets_category_id ?? $this->Category->nf_tickets_category_id ?? false);
	}

	public function getLinkedTicketCategory()
	{
		if (!$this->hasLinkedTicketCategory())
		{
			return null;
		}

		if ($this->nf_tickets_category_id)
		{
			return $this->TicketCategory;
		}

		return $this->Category->TicketCategory;
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure = parent::getStructure($structure);

		$structure->columns['nf_tickets_category_id'] = ['type' => self::UINT, 'nullable' => true];

		$structure->getters['nf_linked_ticket_category'] = ['getter' => 'getLinkedTicketCategory', 'cache' => false];

		$structure->relations['TicketCategory'] = [
			'entity' => 'NF\Tickets:Category',
			'type' => self::TO_ONE,
			'conditions' => [
				['ticket_category_id', '=', '$nf_tickets_category_id'],
			],
			'primary' => true,
		];

		return $structure;
	}
}
