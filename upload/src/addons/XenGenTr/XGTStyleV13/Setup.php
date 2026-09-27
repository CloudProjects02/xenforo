<?php

namespace XenGenTr\XGTStyleV13;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$sm = $this->schemaManager();

		if (!$sm->columnExists('xf_node', 'xgt_grid_etkin'))
		{
			$sm->alterTable('xf_node', function (Alter $table)
			{
				$table->addColumn('xgt_grid_etkin', 'tinyint')->setDefault(0);
			});
		}

		if (!$sm->columnExists('xf_node', 'xgt_forum_renk'))
		{
			$sm->alterTable('xf_node', function (Alter $table)
			{
				$table->addColumn('xgt_forum_renk', 'varchar', 50)->nullable();
			});
		}
	}

	public function installStep2()
	{
		$this->createWidget('xgtSv13_yeni_kullanicilar', 'xgtSv13_yeniKullanici_wd', [
			'positions' => []
		]);
		$this->createWidget('xgtSv13_sosyal_butonlar', 'xgtSv13_sosyalButonlar_wd', [
			'positions' => []
		]);
		$this->createWidget('xgtSv13_infoBox', 'xgtSv13_infoBox_wd', [
			'positions' => []
		]);
	}

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		if ($sm->columnExists('xf_node', 'xgt_grid_etkin'))
		{
			$sm->alterTable('xf_node', function (Alter $table)
			{
				$table->dropColumns('xgt_grid_etkin');
			});
		}

		if ($sm->columnExists('xf_node', 'xgt_forum_renk'))
		{
			$sm->alterTable('xf_node', function (Alter $table)
			{
				$table->dropColumns('xgt_forum_renk');
			});
		}
	}

	public function uninstallStep2()
	{
		$this->deleteWidget('xgtSv13_yeni_kullanicilar');
		$this->deleteWidget('xgtSv13_sosyal_butonlar');
		$this->deleteWidget('xgtSv13_infoBox');
	}
}
