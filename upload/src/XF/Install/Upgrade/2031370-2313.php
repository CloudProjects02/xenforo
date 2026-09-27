<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2031370 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.13';
	}

	public function step1(): void
	{
		$this->alterTable('xf_unfurl_result', function (Alter $table): void
		{
			$table->addColumn('unfurl_key', 'varbinary', 32)->setDefault('')->after('url_hash');
		});
	}

	/**
	 * @param array{} $stepData
	 *
	 * @return array{int, int, array{}}|bool
	 */
	public function step2(int $position, array $stepData)
	{
		$db = $this->db();

		$resultIds = $db->fetchAllColumn(
			$db->limit(
				'SELECT result_id
					FROM xf_unfurl_result
					WHERE result_id > ? AND unfurl_key = ?
					ORDER BY result_id ASC',
				500
			),
			[$position, '']
		);
		if (!$resultIds)
		{
			return true;
		}

		$next = $position;

		foreach ($resultIds AS $resultId)
		{
			$next = $resultId;

			$db->update(
				'xf_unfurl_result',
				['unfurl_key' => \XF::generateRandomString(32)],
				'result_id = ? AND unfurl_key = ?',
				[$resultId, '']
			);
		}

		return [$next, $next, $stepData];
	}
}
