<?php

namespace XF\Job;

use XF\Phrase;
use XF\Repository\PostRepository;

class MessageCount extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'SELECT user_id
					FROM xf_user
					WHERE user_id > ?
					ORDER BY user_id',
				$batch
			),
			$start
		);
	}

	protected function rebuildById($id): void
	{
		$postRepo = $this->app->repository(PostRepository::class);
		$count = $postRepo->getUserPostCount($id);

		$this->app->db()->update(
			'xf_user',
			['message_count' => $count],
			'user_id = ?',
			$id
		);
	}

	protected function getStatusType(): Phrase
	{
		return \XF::phrase('message_counts');
	}
}
