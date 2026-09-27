<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2030871 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.8';
	}

	public function step1(): void
	{
		$this->alterTable('xf_code_event', function (Alter $table): void
		{
			$table->addColumn('arguments', 'mediumblob')->nullable()->after('description');
			$table->addColumn('hint_description', 'text')->nullable()->after('arguments');
		});
	}
}
