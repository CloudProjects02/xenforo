<?php

namespace XenSupport\Staff;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	// ============================================================================
	//  XenStaff free (v1.x) + Pro (v2.x) live in the same addon.
	//   - Free customers running v1.x have ONLY the free columns on
	//     xf_xs_staff_profile and the xf_xs_staff_thank table.
	//   - Uploading the Pro ZIP performs a standard XF upgrade (v1.x -> v2.0.0)
	//     which runs the upgrade2000010Step* methods below to add the Pro
	//     columns + the four Pro tables, preserving all existing free data.
	//   - Fresh Pro installs go straight through installStep1..6 which create
	//     everything at once.
	// ============================================================================

	// ============== Free: profile (Pro install creates it with Pro columns too) ==
	public function installStep1()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_profile')) { return; }
		$sm->createTable('xf_xs_staff_profile', function (Create $table)
		{
			// Free columns
			$table->addColumn('user_id',        'int')->primaryKey();
			$table->addColumn('quote',          'varchar', 280)->setDefault('')->comment('Personal quote shown on the staff page');
			$table->addColumn('social_discord', 'varchar', 100)->setDefault('');
			$table->addColumn('social_twitter', 'varchar', 100)->setDefault('');
			$table->addColumn('social_twitch',  'varchar', 100)->setDefault('');
			$table->addColumn('social_youtube', 'varchar', 100)->setDefault('');
			$table->addColumn('social_instagram','varchar', 100)->setDefault('');
			$table->addColumn('social_github',  'varchar', 100)->setDefault('');
			$table->addColumn('social_website', 'varchar', 255)->setDefault('');
			$table->addColumn('accent_color',   'varchar', 20)->setDefault('')->comment('Hex color used as the card accent');
			$table->addColumn('thanks_count',   'int')->setDefault(0);
			$table->addColumn('last_edit_date', 'int')->setDefault(0);

			// Pro columns (also created on fresh Pro install)
			// BLOB/TEXT columns must NOT declare a SQL DEFAULT: MySQL < 8.0.13 (and other
			// strict engines) reject it with error 1101. The entity defaults rich_bio to ''.
			$table->addColumn('rich_bio',                'mediumblob');
			$table->addColumn('bio_format',              'enum', ['bbcode', 'html'])->setDefault('bbcode');
			$table->addColumn('timezone',                'varchar', 50)->setDefault('UTC');
			$table->addColumn('allow_contact',           'tinyint')->setDefault(1);
			$table->addColumn('allow_anonymous_contact', 'tinyint')->setDefault(1);
			$table->addColumn('online_override',         'enum', ['auto', 'online', 'busy', 'away', 'offline'])->setDefault('auto');
			$table->addColumn('badge_id',                'int')->setDefault(0);
			$table->addColumn('total_responses',         'int')->setDefault(0);
			$table->addColumn('total_response_seconds',  'bigint')->setDefault(0);
			$table->addColumn('contact_received_count',  'int')->setDefault(0);

			$table->addKey('thanks_count');
			$table->addKey('badge_id');
		});
	}

	// ============== Free: thank ==============
	public function installStep2()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_thank')) { return; }
		$sm->createTable('xf_xs_staff_thank', function (Create $table)
		{
			$table->addColumn('thank_id',     'int')->autoIncrement();
			$table->addColumn('from_user_id', 'int');
			$table->addColumn('to_user_id',   'int');
			$table->addColumn('thank_date',   'int')->setDefault(0);
			$table->addUniqueKey(['from_user_id', 'to_user_id'], 'unique_thank');
			$table->addKey('to_user_id');
			$table->addKey('thank_date');
		});
	}

	// ============== Pro: office hours ==============
	public function installStep3()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_hours')) { return; }
		$sm->createTable('xf_xs_staff_hours', function (Create $table)
		{
			$table->addColumn('hour_id',     'int')->autoIncrement();
			$table->addColumn('user_id',     'int');
			$table->addColumn('day_of_week', 'tinyint')->comment('0=Sun ... 6=Sat');
			$table->addColumn('start_time',  'smallint')->comment('Minutes from midnight');
			$table->addColumn('end_time',    'smallint');
			$table->addColumn('note',        'varchar', 100)->setDefault('');
			$table->addPrimaryKey('hour_id');
			$table->addKey(['user_id', 'day_of_week', 'start_time']);
		});
	}

	// ============== Pro: contact form ==============
	public function installStep4()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_contact')) { return; }
		$sm->createTable('xf_xs_staff_contact', function (Create $table)
		{
			$table->addColumn('contact_id',    'int')->autoIncrement();
			$table->addColumn('from_user_id',  'int')->setDefault(0);
			$table->addColumn('from_email',    'varchar', 120)->setDefault('');
			$table->addColumn('to_user_id',    'int');
			$table->addColumn('subject',       'varchar', 150);
			$table->addColumn('message',       'mediumblob');
			$table->addColumn('is_anonymous',  'tinyint')->setDefault(0);
			$table->addColumn('status',        'enum', ['open', 'replied', 'closed'])->setDefault('open');
			$table->addColumn('replied_date',  'int')->setDefault(0);
			$table->addColumn('reply_seconds', 'int')->setDefault(0);
			$table->addColumn('ip_hash',       'varchar', 32)->setDefault('');
			$table->addColumn('created_date',  'int')->setDefault(0);
			$table->addPrimaryKey('contact_id');
			$table->addKey(['to_user_id', 'status', 'created_date']);
			$table->addKey(['from_user_id', 'created_date']);
		});
	}

	// ============== Pro: career timeline ==============
	public function installStep5()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_timeline')) { return; }
		$sm->createTable('xf_xs_staff_timeline', function (Create $table)
		{
			$table->addColumn('event_id',     'int')->autoIncrement();
			$table->addColumn('user_id',      'int');
			$table->addColumn('event_type',   'enum', ['joined','promoted','paused','returned','milestone','custom'])->setDefault('custom');
			$table->addColumn('event_title',  'varchar', 100);
			$table->addColumn('event_note',   'varchar', 500)->setDefault('');
			$table->addColumn('event_date',   'int');
			$table->addColumn('icon',         'varchar', 50)->setDefault('');
			$table->addColumn('created_date', 'int')->setDefault(0);
			$table->addColumn('is_visible',   'tinyint')->setDefault(1);
			$table->addPrimaryKey('event_id');
			$table->addKey(['user_id', 'event_date']);
		});
	}

	// ============== Pro: badge library ==============
	public function installStep6()
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('xf_xs_staff_badge')) { return; }
		$sm->createTable('xf_xs_staff_badge', function (Create $table)
		{
			$table->addColumn('badge_id',       'int')->autoIncrement();
			$table->addColumn('title',          'varchar', 100);
			$table->addColumn('description',    'varchar', 500)->setDefault('');
			$table->addColumn('icon',           'varchar', 50)->setDefault('');
			$table->addColumn('image_url',      'varchar', 255)->setDefault('');
			$table->addColumn('color',          'varchar', 20)->setDefault('#fbbf24');
			$table->addColumn('assigned_count', 'int')->setDefault(0);
			$table->addColumn('display_order',  'int')->setDefault(0);
			$table->addColumn('created_date',   'int')->setDefault(0);
			$table->addPrimaryKey('badge_id');
			$table->addKey('display_order');
		});
	}

	// ============================================================================
	//  Upgrade path: Free v1.x -> Pro v2.0.0
	//
	//  XF only runs upgrade steps whose version is greater than the previously-
	//  installed version_id, so customers on 1.0.0 / 1.0.1 will run all of these
	//  exactly once when they upload the v2.0.0 (Pro) ZIP.
	// ============================================================================

	/**
	 * v2.0.0 step 1 - add the 10 Pro columns to the existing free profile table.
	 * Free customers' rows keep their values; new columns get their default.
	 */
	public function upgrade2000010Step1()
	{
		$sm = $this->schemaManager();
		if (!$sm->tableExists('xf_xs_staff_profile')) { return; }

		$sm->alterTable('xf_xs_staff_profile', function (Alter $table)
		{
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'rich_bio'))
			{
				$table->addColumn('rich_bio', 'mediumblob'); // no SQL DEFAULT: BLOB can't have one (MySQL err 1101)
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'bio_format'))
			{
				$table->addColumn('bio_format', 'enum', ['bbcode', 'html'])->setDefault('bbcode');
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'timezone'))
			{
				$table->addColumn('timezone', 'varchar', 50)->setDefault('UTC');
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'allow_contact'))
			{
				$table->addColumn('allow_contact', 'tinyint')->setDefault(1);
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'allow_anonymous_contact'))
			{
				$table->addColumn('allow_anonymous_contact', 'tinyint')->setDefault(1);
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'online_override'))
			{
				$table->addColumn('online_override', 'enum', ['auto', 'online', 'busy', 'away', 'offline'])->setDefault('auto');
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'badge_id'))
			{
				$table->addColumn('badge_id', 'int')->setDefault(0);
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'total_responses'))
			{
				$table->addColumn('total_responses', 'int')->setDefault(0);
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'total_response_seconds'))
			{
				$table->addColumn('total_response_seconds', 'bigint')->setDefault(0);
			}
			if (!$this->schemaManager()->columnExists('xf_xs_staff_profile', 'contact_received_count'))
			{
				$table->addColumn('contact_received_count', 'int')->setDefault(0);
			}
		});

		// Add the badge_id key if it doesn't exist
		$db = $this->db();
		$keyExists = $db->fetchOne(
			"SELECT COUNT(*) FROM information_schema.statistics
			 WHERE table_schema = DATABASE()
			   AND table_name = 'xf_xs_staff_profile'
			   AND index_name = 'badge_id'"
		);
		if (!$keyExists)
		{
			try { $db->query("ALTER TABLE xf_xs_staff_profile ADD INDEX badge_id (badge_id)"); }
			catch (\Throwable $e) { /* race or already exists */ }
		}
	}

	/** v2.0.0 step 2 - create xf_xs_staff_hours */
	public function upgrade2000010Step2() { $this->installStep3(); }

	/** v2.0.0 step 3 - create xf_xs_staff_contact */
	public function upgrade2000010Step3() { $this->installStep4(); }

	/** v2.0.0 step 4 - create xf_xs_staff_timeline */
	public function upgrade2000010Step4() { $this->installStep5(); }

	/** v2.0.0 step 5 - create xf_xs_staff_badge */
	public function upgrade2000010Step5() { $this->installStep6(); }

	/**
	 * v2.0.0 step 6 - one-shot migration from the (never-shipped) StaffPro
	 * standalone addon. If a client has the legacy xf_xs_staffpro_* tables
	 * lying around from a private beta, copy their Pro data into the unified
	 * xf_xs_staff_* tables and drop the legacy ones.
	 *
	 * No-op for everyone else (the vast majority).
	 */
	public function upgrade2000010Step6()
	{
		$db = $this->db();
		$sm = $this->schemaManager();

		if (!$sm->tableExists('xf_xs_staffpro_profile')) { return; }

		// Carry forward Pro fields from the legacy table into rows that
		// already exist in xf_xs_staff_profile (the free row is the source
		// of truth for free columns).
		$db->query("
			UPDATE xf_xs_staff_profile p
			INNER JOIN xf_xs_staffpro_profile sp ON sp.user_id = p.user_id
			SET p.rich_bio                = sp.rich_bio,
			    p.bio_format              = sp.bio_format,
			    p.timezone                = sp.timezone,
			    p.allow_contact           = sp.allow_contact,
			    p.allow_anonymous_contact = sp.allow_anonymous_contact,
			    p.online_override         = sp.online_override,
			    p.badge_id                = sp.badge_id,
			    p.total_responses         = sp.total_responses,
			    p.total_response_seconds  = sp.total_response_seconds,
			    p.contact_received_count  = sp.contact_received_count
		");

		// For rows that exist in staffpro but NOT in free, insert them.
		$db->query("
			INSERT INTO xf_xs_staff_profile
				(user_id, quote, social_discord, social_twitter, social_twitch,
				 social_youtube, social_instagram, social_github, social_website,
				 accent_color, thanks_count, last_edit_date,
				 rich_bio, bio_format, timezone, allow_contact, allow_anonymous_contact,
				 online_override, badge_id, total_responses, total_response_seconds,
				 contact_received_count)
			SELECT sp.user_id, sp.quote, sp.social_discord, sp.social_twitter, sp.social_twitch,
				   sp.social_youtube, sp.social_instagram, sp.social_github, sp.social_website,
				   sp.accent_color, sp.thanks_count, sp.last_edit_date,
				   sp.rich_bio, sp.bio_format, sp.timezone, sp.allow_contact, sp.allow_anonymous_contact,
				   sp.online_override, sp.badge_id, sp.total_responses, sp.total_response_seconds,
				   sp.contact_received_count
			FROM xf_xs_staffpro_profile sp
			LEFT JOIN xf_xs_staff_profile p ON p.user_id = sp.user_id
			WHERE p.user_id IS NULL
		");

		// Thanks: dedupe against existing rows via INSERT IGNORE
		if ($sm->tableExists('xf_xs_staffpro_thank'))
		{
			$db->query("
				INSERT IGNORE INTO xf_xs_staff_thank (from_user_id, to_user_id, thank_date)
				SELECT from_user_id, to_user_id, thank_date FROM xf_xs_staffpro_thank
			");
		}

		// Hours / contact / timeline / badge: straight copy (these tables didn't
		// exist in free, so no collision possible).
		foreach (['hours', 'contact', 'timeline', 'badge'] AS $name)
		{
			$proTable = "xf_xs_staffpro_{$name}";
			$newTable = "xf_xs_staff_{$name}";
			if (!$sm->tableExists($proTable) || !$sm->tableExists($newTable)) { continue; }

			// Use SELECT * to copy verbatim — the schemas match by design
			$db->query("INSERT INTO {$newTable} SELECT * FROM {$proTable}");
		}

		// Drop the legacy staffpro tables now that the data is safe
		foreach (['xf_xs_staffpro_badge', 'xf_xs_staffpro_timeline', 'xf_xs_staffpro_contact',
		          'xf_xs_staffpro_hours', 'xf_xs_staffpro_thank', 'xf_xs_staffpro_profile'] AS $tbl)
		{
			if ($sm->tableExists($tbl))
			{
				$sm->dropTable($tbl);
			}
		}

		// If the legacy XenSupport/StaffPro addon entity still exists in xf_addon,
		// scrub every DB row tied to it. After this the merge is invisible to
		// the customer (ACP won't show "files missing" or orphan permissions).
		try
		{
			$proId = 'XenSupport/StaffPro';
			$tablesByAddon = [
				'xf_addon', 'xf_phrase', 'xf_template', 'xf_template_modification',
				'xf_class_extension', 'xf_code_event_listener', 'xf_widget_definition',
				'xf_style_property_group', 'xf_style_property', 'xf_help_page',
				'xf_admin_navigation', 'xf_navigation', 'xf_cron_entry',
				'xf_bb_code', 'xf_bb_code_media_site', 'xf_advertising_position',
				'xf_member_stat', 'xf_content_type_field', 'xf_activity_summary_definition',
				'xf_api_scope', 'xf_admin_permission', 'xf_permission_interface_group',
			];
			foreach ($tablesByAddon AS $t)
			{
				try { $db->delete($t, 'addon_id = ?', $proId); }
				catch (\Throwable $e) { /* table may not exist on older XF */ }
			}
			$db->delete('xf_option', "option_id LIKE 'xenStaffPro%'");
			$db->delete('xf_option_group', 'group_id = ?', 'xenStaffPro');
			$db->delete('xf_permission', 'permission_group_id = ?', 'xenStaffPro');
			$db->delete('xf_admin_navigation', "navigation_id LIKE 'xenStaffPro%'");
			$db->delete('xf_navigation', "navigation_id LIKE 'xenStaffPro%'");
			// XF 2.3 uses xf_route (not xf_route_prefix). Defensive: try both names
			// so older XF installs aren't broken if the schema ever shifts.
			foreach (['xf_route', 'xf_route_prefix'] AS $routeTbl)
			{
				try { $db->delete($routeTbl, "addon_id = ?", $proId); }
				catch (\Throwable $e) { /* table doesn't exist on this XF version */ }
			}
			// Stranded reactions/alerts (defensive)
			$db->delete('xf_user_alert', "content_type LIKE 'xs_staffpro%'");
		}
		catch (\Throwable $e) { /* don't block upgrade if any cleanup row fails */ }
	}

	/**
	 * v2.0.2 - clean up orphan alerts left by the StaffPro -> Staff merge.
	 *
	 * The standalone StaffPro add-on sent contact alerts under the action
	 * 'xs_staffpro_contact'. After the merge that action was renamed to
	 * 'xs_staff_contact', so the old alert template no longer exists and every
	 * time an affected user opens their alert list XF logs:
	 *   "Template public:alert_user_xs_staffpro_contact is unknown"
	 * Those rows are dead (nothing will ever render them again), so delete them.
	 * Safe to re-run. Current 'xs_staff_contact' alerts are kept on purpose -
	 * their template (alert_user_xs_staff_contact) ships in this same version.
	 */
	public function upgrade2000210Step1()
	{
		$this->db()->delete('xf_user_alert', 'action = ?', ['xs_staffpro_contact']);
	}

	// ============================================================================
	//  Uninstall + safety net
	// ============================================================================

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();
		foreach (['xf_xs_staff_badge', 'xf_xs_staff_timeline', 'xf_xs_staff_contact',
		          'xf_xs_staff_hours', 'xf_xs_staff_thank', 'xf_xs_staff_profile'] AS $table)
		{
			if ($sm->tableExists($table))
			{
				$sm->dropTable($table);
			}
		}
	}

	public function uninstallStep2()
	{
		// Remove our alerts so they don't keep triggering
		//   "Template public:alert_user_xs_staff_contact is unknown"
		// once this add-on's templates are gone. We also clear the legacy
		// 'xs_staffpro_contact' action left behind by the pre-merge StaffPro
		// standalone add-on, whose template no longer exists.
		$this->db()->delete('xf_user_alert', 'action IN (?, ?)', ['xs_staff_contact', 'xs_staffpro_contact']);
	}

	public function ensureCompleteSchema()
	{
		$sm = $this->schemaManager();
		if (!$sm->tableExists('xf_xs_staff_profile'))  { $this->installStep1(); }
		if (!$sm->tableExists('xf_xs_staff_thank'))    { $this->installStep2(); }
		if (!$sm->tableExists('xf_xs_staff_hours'))    { $this->installStep3(); }
		if (!$sm->tableExists('xf_xs_staff_contact'))  { $this->installStep4(); }
		if (!$sm->tableExists('xf_xs_staff_timeline')) { $this->installStep5(); }
		if (!$sm->tableExists('xf_xs_staff_badge'))    { $this->installStep6(); }

		// Belt-and-suspenders: if someone upgraded from 1.x but the column-add
		// step somehow didn't run, run it now.
		if ($sm->tableExists('xf_xs_staff_profile')
			&& !$sm->columnExists('xf_xs_staff_profile', 'rich_bio'))
		{
			$this->upgrade2000010Step1();
		}
	}

	public function postInstall(array &$stateChanges)
	{
		$this->ensureCompleteSchema();
	}

	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		$this->ensureCompleteSchema();
	}
}
