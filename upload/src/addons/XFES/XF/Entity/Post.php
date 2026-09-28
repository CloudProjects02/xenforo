<?php

namespace XFES\XF\Entity;

class Post extends XFCP_Post
{
	protected function _postSave()
	{
		parent::_postSave();

		if (
			$this->isFirstPost()
			&& $this->isUpdate()
			&& $this->isChanged('message')
		)
		{
			$this->db()->delete(
				'xf_es_thread_similar',
				'thread_id = ?',
				$this->thread_id
			);
		}
	}
}
