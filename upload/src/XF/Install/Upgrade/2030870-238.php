<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Repository\AdminNavigationRepository;
use XF\Repository\OptionRepository;

class Version2030870 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.8 (Preview)';
	}

	public function step1(): void
	{
		$this->executeUpgradeQuery('
			DELETE
			FROM xf_passkey
			WHERE user_id NOT IN (SELECT user_id FROM xf_user)
		');
	}

	public function step2(): void
	{
		$this->alterTable('xf_attachment_data', function (Alter $table): void
		{
			$table->addColumn('thumbnail_retina', 'tinyint')->setDefault(0)->after('thumbnail_height');
		});
	}

	public function step3(): void
	{
		$this->alterTable('xf_passkey', function (Alter $table): void
		{
			$table->addColumn('signature_counter', 'int')->setDefault(0)->after('aaguid');
		});
	}

	public function step4(): void
	{
		$dkimOptions = \XF::options()->emailDkim;

		if ($dkimOptions && !empty($dkimOptions['enabled']))
		{
			$dkimOptions['selector'] = 'xenforo';

			\XF::db()->update(
				'xf_option',
				['sub_options' => "enabled\nverified\nfailed\ndomain\nprivateKey\nselector"],
				'option_id = ?',
				['emailDkim']
			);

			$optionRepo = $this->app->repository(OptionRepository::class);
			$optionRepo->updateOption('emailDkim', $dkimOptions);
		}
	}

	public function step5(): void
	{
		$this->alterTable('xf_admin_navigation', function (Alter $table): void
		{
			$table->addColumn('super_admin_only', 'tinyint')->setDefault(0)->after('development_only');
		});
	}

	public function step6(): void
	{
		$this->executeUpgradeQuery("
			UPDATE xf_admin_navigation
			SET super_admin_only = 1
			WHERE navigation_id = 'admins'
		");
	}

	public function step7(): void
	{
		$navRepo = $this->app->repository(AdminNavigationRepository::class);
		$navRepo->rebuildNavigationCache();
	}
}
