<?php

namespace EAEAddons\ThreadCount\XF\Entity;

use XF\Mvc\Entity\Structure;

class Thread extends XFCP_Thread
{
	public function canView(&$error = null)
	{
		$parent = parent::canView($error);
		if (!$parent)
		{
			return false;
		}
		$visitor = \XF::visitor();

		if ($visitor->user_id != $this->user_id)
		{
			$viewLimit = $visitor->hasNodePermission($this->node_id, 'viewOthersThreadCount');
			if (!$viewLimit)
			{
				return true;
			}
			return $visitor->eaetc_thread_count >= $viewLimit;
		}
		return true;
	}

	public function canReply(&$error = null)
	{
		$parent = parent::canReply($error);
		if (!$parent)
		{
			return false;
		}
		$visitor = \XF::visitor();

		$viewLimit = $visitor->hasNodePermission($this->node_id, 'replyThreadCount');
		if (!$viewLimit)
		{
			return true;
		}

		if ($visitor->user_id != $this->user_id)
		{
			return $visitor->eaetc_thread_count >= $viewLimit;
		}
		return $parent;
	}

    protected function _postSave()
    {
		$visibilityChange = $this->isStateChanged('discussion_state', 'visible');
		if ($this->isInsert() && $this->discussion_state == 'visible')
		{
			if ($this->user_id && !empty($this->Forum->count_messages))
			{
				$this->adjustUserThreadCountIfNeeded(1);
			}
		}

		if ($this->isUpdate() && $this->user_id)
		{
			if ($visibilityChange == 'leave')
			{
				if (!empty($this->Forum->count_messages))
				{
					$this->adjustUserThreadCountIfNeeded(-1);
				}
			}
			else
			{
				if ($this->discussion_state == 'visible' && $visibilityChange && !empty($this->Forum->count_messages))
				{
					$this->adjustUserThreadCountIfNeeded(1);
				}
			}

			if ($this->isChanged('node_id'))
			{
				$oldForum = $this->getExistingRelation('Forum');
				if ($oldForum && $this->Forum)
				{
					if (!$this->isStateChanged('discussion_state', 'visible'))
					{
						if ($oldForum->count_messages != $this->Forum->count_messages)
						{
							if (empty($oldForum->count_messages) && !empty($this->Forum->count_messages))
							{
								if ($this->discussion_state == 'visible')
								{
									$this->adjustUserThreadCountIfNeeded(1);
								}
							}
							else
							{
								if ($this->discussion_state == 'visible')
								{
									$this->adjustUserThreadCountIfNeeded(-1);
								}
							}
						}
					}
				}
			}
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		if ($this->discussion_state == 'visible' && $this->user_id && !empty($this->Forum->count_messages))
		{
			$this->adjustUserThreadCountIfNeeded(-1);
		}
		return parent::_postDelete();
	}

	protected function adjustUserThreadCountIfNeeded($amount)
	{
		if ($this->discussion_type == 'redirect')
		{
			return;
		}

		$this->db()->query("
			UPDATE xf_user
			SET eaetc_thread_count = GREATEST(0, eaetc_thread_count + ?)
			WHERE user_id = ?
			", [$amount, $this->user_id]
		);

		$userEntity = $this->em()->findCached('XF:User', $this->user_id);
		if ($userEntity)
		{
			$userEntity->setAsSaved('eaetc_thread_count', max(0, $userEntity->eaetc_thread_count + $amount));
		}
    }
}