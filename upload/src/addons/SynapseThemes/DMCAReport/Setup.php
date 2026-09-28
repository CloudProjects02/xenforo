<?php

namespace SynapseThemes\DMCAReport;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->schemaManager()->createTable('xf_st_dmca_report', function(Create $table)
		{
			$table->addColumn('report_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 255)->setDefault('');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('email', 'varchar', 120);
			$table->addColumn('content_urls', 'mediumtext');
			$table->addColumn('original_content_urls', 'mediumtext');
			$table->addColumn('content_description', 'text');
			$table->addColumn('copyright_owner', 'varchar', 255);
			$table->addColumn('dmca_type', 'varchar', 50)->setDefault('copyright');
			$table->addColumn('status', 'varchar', 25)->setDefault('pending');
			$table->addColumn('admin_notes', 'text');
			$table->addColumn('ip_address', 'varchar', 45)->setDefault('');
			$table->addColumn('submit_date', 'int')->setDefault(0);
			$table->addColumn('review_date', 'int')->setDefault(0);
			$table->addColumn('reviewer_id', 'int')->setDefault(0);
			
			$table->addKey('user_id');
			$table->addKey('status');
			$table->addKey('submit_date');
		});
	}

	public function uninstallStep1()
	{
		$this->schemaManager()->dropTable('xf_st_dmca_report');
	}
}