<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\AddOnRepository;
use XF\Repository\CodeEventListenerRepository;

/**
 * COLUMNS
 * @property string $event_id
 * @property string $description
 * @property array|null $arguments
 * @property string|null $hint_description
 * @property string $addon_id
 *
 * GETTERS
 * @property-read string $callback_signature
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class CodeEvent extends Entity
{
	public function getCallbackSignature(): string
	{
		$arguments = $this->arguments;
		if (empty($arguments))
		{
			return '';
		}

		$params = [];
		foreach ($arguments AS $arg)
		{
			$name = $arg['name'] ?? '';
			$type = $arg['type'] ?? '';

			if (empty($name))
			{
				continue;
			}

			$byRef = str_starts_with($name, '&');
			if ($byRef)
			{
				$name = substr($name, 1);
			}

			if (!str_starts_with($name, '$'))
			{
				$name = '$' . $name;
			}

			$param = '';
			if (!empty($type))
			{
				$param .= $type . ' ';
			}
			if ($byRef)
			{
				$param .= '&';
			}
			$param .= $name;

			$params[] = $param;
		}

		return implode(', ', $params);
	}

	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('event_id'))
		{
			$this->db()->update('xf_code_event_listener', [
				'event_id' => $this->event_id,
			], 'event_id = ?', $this->getExistingValue('event_id'));
			$this->rebuildListenerCache();
		}
	}

	protected function _postDelete()
	{
		$this->db()->delete('xf_code_event_listener', 'event_id = ?', $this->event_id);
		$this->rebuildListenerCache();
	}

	protected function rebuildListenerCache()
	{
		$repo = $this->getListenerRepo();

		\XF::runOnce('codeEventListenerCacheRebuild', function () use ($repo)
		{
			$repo->rebuildListenerCache();
		});
	}

	protected function _setupDefaults()
	{
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_code_event';
		$structure->shortName = 'XF:CodeEvent';
		$structure->primaryKey = 'event_id';
		$structure->columns = [
			'event_id' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_code_event_id',
				'unique' => 'code_event_ids_must_be_unique',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'description' => ['type' => self::STR, 'default' => ''],
			'arguments' => ['type' => self::JSON_ARRAY, 'default' => [], 'nullable' => true],
			'hint_description' => ['type' => self::STR, 'default' => '', 'nullable' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'required' => true],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'callback_signature' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return CodeEventListenerRepository
	 */
	protected function getListenerRepo()
	{
		return $this->repository(CodeEventListenerRepository::class);
	}
}
