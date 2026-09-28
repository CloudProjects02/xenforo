<?php

namespace XFES\XF\Entity;

use XF\Mvc\Entity\Structure;

class Thread extends XFCP_Thread
{
	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isUpdate() && $this->isChanged('title'))
		{
			$this->db()->delete(
				'xf_es_thread_similar',
				'thread_id = ?',
				$this->thread_id
			);
		}
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations['XFES_SimilarThreads'] = [
			'entity' => 'XFES:ThreadSimilar',
			'type' => self::TO_ONE,
			'conditions' => 'thread_id',
			'primary' => true,
			'cascadeDelete' => true,
		];

		return $structure;
	}
}
