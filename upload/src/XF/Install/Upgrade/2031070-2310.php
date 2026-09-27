<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2031070 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.10';
	}

	public function step1(): void
	{
		$this->alterTable('xf_bookmark_label_use', function (Alter $table): void
		{
			$table->addKey('bookmark_id');
		});
	}
}
