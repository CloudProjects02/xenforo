<?php

namespace XF\AddOn\DataType;

use XF\Behavior\DevOutputWritable;
use XF\Entity\AddOn;
use XF\Repository\CodeEventListenerRepository;

class CodeEvent extends AbstractDataType
{
	public function getShortName()
	{
		return 'XF:CodeEvent';
	}

	public function getContainerTag()
	{
		return 'code_events';
	}

	public function getChildTag()
	{
		return 'event';
	}

	public function exportAddOnData($addOnId, \DOMElement $container)
	{
		$entries = $this->finder()
			->where('addon_id', $addOnId)
			->order('event_id')->fetch();

		foreach ($entries AS $entry)
		{
			$node = $container->ownerDocument->createElement($this->getChildTag());

			$this->exportMappedAttributes($node, $entry);
			$this->exportCdata($node, $entry->description);

			if (!empty($entry->arguments))
			{
				$node->setAttribute('arguments', json_encode($entry->arguments));
			}

			if (!empty($entry->hint_description))
			{
				$node->setAttribute('hint_description', $entry->hint_description);
			}

			$container->appendChild($node);
		}

		return $entries->count() ? true : false;
	}

	public function importAddOnData($addOnId, \SimpleXMLElement $container, $start = 0, $maxRunTime = 0)
	{
		$startTime = microtime(true);

		$entries = $this->getEntries($container, $start);
		if (!$entries)
		{
			return false;
		}

		$ids = $this->pluckXmlAttribute($entries, 'event_id');
		$existing = $this->findByIds($ids);

		$i = 0;
		$last = 0;
		foreach ($entries AS $entry)
		{
			$id = $ids[$i++];

			if ($i <= $start)
			{
				continue;
			}

			$entity = $existing[$id] ?? $this->create();

			$entity->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);
			$this->importMappedAttributes($entry, $entity);
			$entity->description = $this->getCdataValue($entry);
			$entity->addon_id = $addOnId;

			$argumentsAttr = (string) $entry['arguments'];
			if ($argumentsAttr !== '')
			{
				$entity->arguments = json_decode($argumentsAttr, true) ?: [];
			}

			$hintDescAttr = (string) $entry['hint_description'];
			if ($hintDescAttr !== '')
			{
				$entity->hint_description = $hintDescAttr;
			}

			$entity->save(true, false);

			if ($this->resume($maxRunTime, $startTime))
			{
				$last = $i;
				break;
			}
		}
		return ($last ?: false);
	}

	public function deleteOrphanedAddOnData($addOnId, \SimpleXMLElement $container)
	{
		$this->deleteOrphanedSimple($addOnId, $container, 'event_id');
	}

	public function rebuildActiveChange(AddOn $addOn, array &$jobList)
	{
		\XF::runOnce('rebuild_active_code_event_listeners', function ()
		{
			$repo = $this->em->getRepository(CodeEventListenerRepository::class);
			$repo->rebuildListenerCache();
		});
	}

	protected function getMappedAttributes()
	{
		return [
			'event_id',
		];
	}
}
