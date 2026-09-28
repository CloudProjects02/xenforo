<?php

namespace SV\SignupAbuseBlocking;

use SV\SignupAbuseBlocking\Job\LinkUsersToMultiAccountReport;
use SV\SignupAbuseBlocking\Job\MigrateAlterEgoDetectorReports;
use SV\SignupAbuseBlocking\Job\MigrateLoginRecords;
use SV\SignupAbuseBlocking\Job\MigrateSpamTriggerLogs;
use SV\SignupAbuseBlocking\Job\MigrateUserRegLogs;
use SV\SignupAbuseBlocking\Job\RebuildMultiAccountSummary;
use SV\SignupAbuseBlocking\Job\Upgrade\Upgrade1000500Step1;
use SV\SignupAbuseBlocking\Job\Upgrade\Upgrade1000500Step2;
use SV\SignupAbuseBlocking\Repository\AllowEmailDomain;
use SV\SignupAbuseBlocking\Repository\MaxMind as MaxMindRepo;
use SV\SignupAbuseBlocking\Util\LanguageList;
use SV\SignupAbuseBlocking\Util\TimezoneList;
use SV\StandardLib\Helper;
use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\CronEntry as CronEntryEntity;
use XF\Entity\Option as OptionEntity;
use XF\Finder\Option as OptionFinder;
use XF\Entity\User as UserEntity;
use XF\Finder\CronEntry as CronEntryFinder;
use XF\Job\Atomic as AtomicJob;
use XF\Job\PermissionRebuild as PermissionRebuildJob;
use XF\Repository\AdminPermission as AdminPermissionRepo;
use XF\Repository\Option as OptionRepo;
use XF\Repository\User as UserRepo;
use XF\Validator\Email as EmailValidator;
use function array_key_exists;
use function array_unique;
use function count;
use function explode;
use function implode;
use function is_array;
use function preg_match;
use function random_int;
use function sort;
use function strcasecmp;

class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /** @var array<string,bool> */
    public static $supportedAddOns = [
        'XenCentral/MultiSite' => true,
    ];

    /**
     * Creates add-on tables.
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Alters core tables.
     */
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function installStep3()
    {
        // Alter Ego Detector
        $this->cloneOption('liam_aed_cookiename', 'svSockSignupAccountCookie');
        $this->cloneOption('aed_cookie_lifespan', 'svSockSignupAccountCookieLifeSpan');
        $this->cloneOption('aed_banned_logout', 'svAllowBannedLogout');
        $this->cloneOption('aedcheckips', 'svSockSignupCheckMatchingIps');
        $this->cloneOption('aedshowdetectionmethods', 'svSockSignupCheckShowMethods');
        $this->cloneOption('aeduserid', 'svSockSignupCheckReportingUser');
        $this->cloneOption('aed_ip_whitelist', 'svSockSignupCheckIpAllowedList');
        $this->cloneOption('aedcreatethread', 'svSockSignupCheckReportThread');
        $this->cloneOption('aedforumid', 'svSockSignupCheckReportForum');
        $this->cloneOption('aedsendpm', 'svSockSignupCheckReportConv');
        $this->cloneOption('aedpmrecipients', 'svSockSignupCheckReportConvTo');
        $this->cloneOption('aedreport', 'svSockSignupCheckReport');
        $this->cloneOption('aedreport_senddupe', 'svSockSignupCheckDupeReports');
        $this->cloneOption('aedregistrationmode', 'svSockSignupCheckRegMode');
        $this->cloneOption('aedregistrationmode_group', 'svSockSignupCheckRegModeGroup');
        $this->cloneOption('aedregistrationmode_group_ids', 'svSockSignupCheckRegModeGroupIds');
        $this->cloneOption('aed_pm_default_title', 'svSockSignupCheckDefaultConvTitle');

        $this->mapToBasicScore('svSockSignupCheckRegMode', true);
        $this->mapToBasicScore('svSockSignupCheckRegModeGroup', true);
    }

    public function installStep4()
    {
        $this->applyGlobalPermission('multipleAccountHandling', 'bypass', 'general', 'aedbypass');
        $this->applyGlobalPermission('multipleAccountHandling', 'viewreport', 'general', 'aedviewreport');
    }

    public function installStep5()
    {
        $conversationRecipients = $this->db()->fetchOne("
            SELECT option_value
            FROM xf_option
            WHERE option_id = 'svSockSignupCheckReportConvTo'
        ");

        if (@\json_decode($conversationRecipients, true))
        {
            return;
        }

        $conversationRecipients = @str_replace(
            [
                "\r",
                "\r\n"
            ], "\n", $conversationRecipients
        );
        $conversationRecipients = \array_filter(\explode("\n", $conversationRecipients));
        $userIds = [];

        if (\count($conversationRecipients))
        {
            $users = Helper::repository(UserRepo::class)->getUsersByNames($conversationRecipients);
            if ($users->count())
            {
                $userIds = \array_keys($users->groupBy('user_id'));
            }
        }

        $this->query("
            UPDATE xf_option
            SET option_value = ?
            WHERE option_id = 'svSockSignupCheckReportConvTo'
        ", [json_encode($userIds)]);
    }


    public function installStep6()
    {
        $stopForumSpam = Helper::find(OptionEntity::class, 'stopForumSpam');
        $sfsEnabled = $this->getOptionOrDefault('svSpamRegBlockingSFSEnabled', null);
        if ($stopForumSpam && $sfsEnabled)
        {
            // migrate into XF honey-pot config
            $value = $stopForumSpam->option_value;
            $value['enabled'] = 1;
            $value['frequencyCutOff'] = 1;

            $stopForumSpam->option_value = $value;
            $stopForumSpam->saveIfChanged();

            $sfsConfig = Helper::find(OptionEntity::class, 'svSFSExtendedConfig') ?? Helper::createEntity(OptionEntity::class);
            if (!$sfsConfig->option_id)
            {
                $sfsConfig->option_id = 'svSFPExtendedConfig';
                $sfsConfig->setOption('verify_validation_callback', false);
                $sfsConfig->setOption('verify_value', false);
                $sfsConfig->addon_id = $this->addOn->getAddOnId();
                $sfsConfig->edit_format = 'template';
                $sfsConfig->edit_format_params = 'option_template_signup_blocking_sfs';
                $sfsConfig->data_type = 'array';
                $sfsConfig->sub_options = ['*'];
            }

            $config = $sfsConfig->option_value ?: [];
            $this->populateOptionOrDefault($config, 'username', 'svSpamRegBlockingSFSUsername', 2);
            $this->populateOptionOrDefault($config, 'email', 'svSpamRegBlockingSFSEmail', 4);
            $this->populateOptionOrDefault($config, 'ip', 'svSpamRegBlockingSFSIp', 3);
            $sfsConfig->option_value = $config;

            $sfsConfig->save();
        }
    }

    public function installStep7()
    {
        $registrationCheckDnsBl = Helper::find(OptionEntity::class, 'registrationCheckDnsBl');
        $honeyPotKey = $this->getOptionOrDefault('TPUDetectSpamRegHoneyPotAPIKey', null);
        if ($registrationCheckDnsBl && $honeyPotKey)
        {
            // migrate into XF honey-pot config
            $value = $registrationCheckDnsBl->option_value;

            if (!empty(\XF::options()->TPUDetectSpamRegHoneyPotEnabled))
            {
                $value['check'] = 1;
                $value['action'] = 'moderate';
                $value['projectHoneyPotKey'] = $honeyPotKey;
            }
            else if (empty($value['projectHoneyPotKey']))
            {
                $value['projectHoneyPotKey'] = $honeyPotKey;
            }

            $registrationCheckDnsBl->option_value = $value;
            $registrationCheckDnsBl->saveIfChanged();

            $honeyPotConfig = Helper::find(OptionEntity::class, 'svProjectHoneyPotExtendedConfig') ?? Helper::createEntity(OptionEntity::class);
            if (!$honeyPotConfig->option_id)
            {
                $honeyPotConfig->option_id = 'svProjectHoneyPotExtendedConfig';
                $honeyPotConfig->setOption('verify_validation_callback', false);
                $honeyPotConfig->setOption('verify_value', false);
                $honeyPotConfig->addon_id = $this->addOn->getAddOnId();
                $honeyPotConfig->edit_format = 'template';
                $honeyPotConfig->edit_format_params = 'option_template_signup_blocking_honeypot';
                $honeyPotConfig->data_type = 'array';
                $honeyPotConfig->sub_options = ['*'];
            }

            $config = $honeyPotConfig->option_value ?: [];
            $this->populateOptionOrDefault($config, 'cutOff', 'TPUDetectSpamRegHoneyPotCutoff', 60);
            $this->populateOptionOrDefault($config, 'score10to20', 'TPUDetectSpamRegHoneyPotScore10', 1);
            $this->populateOptionOrDefault($config, 'score80to100', 'TPUDetectSpamRegHoneyPotScore80', 6);
            $honeyPotConfig->option_value = $config;

            $honeyPotConfig->save();
        }
    }

    public function installStep8()
    {
        $torConfig = Helper::find(OptionEntity::class, 'svSignupTorBlockingConfig') ?? Helper::createEntity(OptionEntity::class);
        if (!$torConfig->option_id)
        {
            $torConfig->option_id = 'svSignupTorBlockingConfig';
            $torConfig->setOption('verify_validation_callback', false);
            $torConfig->setOption('verify_value', false);
            $torConfig->addon_id = $this->addOn->getAddOnId();
            $torConfig->edit_format = 'template';
            $torConfig->edit_format_params = 'option_template_signup_blocking_tor';
            $torConfig->data_type = 'array';
            $torConfig->sub_options = ['*'];
        }

        $config = $torConfig->option_value ?: [];
        $this->populateOptionOrDefault($config, 'score', 'TPUDetectSpamRegTORScore', 4);
        $this->populateOptionOrDefault($config, 'serverIp', 'TPUDetectSpamRegSrvIp', '');
        $config = \array_merge([
            'cloudflare' => 1
        ], $config);
        $torConfig->option_value = $config;

        $torConfig->save();
    }

    public function installStep9()
    {
        $asnConfig = Helper::find(OptionEntity::class, 'svSignupAsnBlockingConfig') ?? Helper::createEntity(OptionEntity::class);
        if (!$asnConfig->option_id)
        {
            $asnConfig->option_id = 'svSignupAsnBlockingConfig';
            $asnConfig->setOption('verify_validation_callback', false);
            $asnConfig->setOption('verify_value', false);
            $asnConfig->addon_id = $this->addOn->getAddOnId();
            $asnConfig->edit_format = 'template';
            $asnConfig->edit_format_params = 'option_template_signup_blocking_asn';
            $asnConfig->data_type = 'array';
            $asnConfig->sub_options = ['*'];
        }

        $config = $asnConfig->option_value ?: [];
        $this->populateOptionOrDefault($config, 'cymru', 'tpu_asn_cymru', 1);
        $this->populateOptionOrDefault($config, 'ripe', 'tpu_asn_ripe', 1);
        $config = \array_merge([
            'cloudflare' => 1,
            'maxMind' => 1,
        ], $config);

        $asnConfig->option_value = $config;

        $asnConfig->save();
    }

    public function installStep10()
    {
        $geoIpConfig = Helper::find(OptionEntity::class, 'svSignupGeoIPConfig') ?? Helper::createEntity(OptionEntity::class);
        if (!$geoIpConfig->option_id)
        {
            $geoIpConfig->option_id = 'svSignupGeoIPConfig';
            $geoIpConfig->setOption('verify_validation_callback', false);
            $geoIpConfig->setOption('verify_value', false);
            $geoIpConfig->addon_id = $this->addOn->getAddOnId();
            $geoIpConfig->edit_format = 'template';
            $geoIpConfig->edit_format_params = 'option_template_signup_blocking_geoip';
            $geoIpConfig->data_type = 'array';
            $geoIpConfig->sub_options = ['*'];
        }

        $config = $geoIpConfig->option_value ?: [];
        $config = \array_merge([
            'cloudflare' => 1,
            'maxMind' => 1,
            'ipStack' => 0,
            'ipStackKey' => 0,
            'ipApi' => 1,
            'ipregistry' => 0,
        ], $config);
        $geoIpConfig->option_value = $config;

        $geoIpConfig->save();
    }

    public function installStep11()
    {
        // passive score based rules
        $this->cloneOption('TPUDetectSpamRegAS','svSignupAsnBlockingRule');
        $this->cloneOption('TPUDetectSpamRegIPCountry','svSignupCountryBlockingRule');
        $this->cloneOption('TPUDetectSpamRegEmail','svSignupEmailBlockingRule');
        $this->cloneOption('TPUDetectSpamRegUsername','svSignupUsernameBlockingRule');
    }

    public function installStep12()
    {
        // non-passive tests which the user can interfere with
        $this->cloneOption('TPUDetectSpamRegHostname', 'svSignupHostnameBlockingRule');
        $this->cloneOption('TPUDetectSpamRegNoJs', 'svSignupNoJsScore');
        $this->cloneOption('TPUDetectSpamRegTimerMin', 'svSignupFastRegFill');
        $this->cloneOption('TPUDetectSpamRegTimerScore', 'svSignupFastRegFillScore');
        $this->cloneOption('TPUDetectSpamRegOpenPort', 'svSignupOpenPortBlockingRule');

        $this->mapToBasicScore('svSignupNoJsScore', false);
        $this->mapToBasicScore('svSignupFastRegFillScore', false);

        $svSignupFastRegFill = Helper::find(OptionEntity::class,'svSignupFastRegFill');
        if ($svSignupFastRegFill && $svSignupFastRegFill->option_value > 1000)
        {
            $svSignupFastRegFill->option_value = (int)(((int)$svSignupFastRegFill->option_value) / 1000);
            $svSignupFastRegFill->save();
        }
    }

    public function installStep13()
    {
        // TPU Spam Detect misc options
        $this->cloneOption('TPUDetectSpamRegScoreMod', 'svSignupModerateScoreThreshold');
        $this->cloneOption('TPUDetectSpamRegScoreRej', 'svSignupRejectScoreThreshold');
        //$this->cloneOption('TPUDetectSpamRegScoreModPosts', 'svSignupModeratePostsScoreThreshold');
    }


    public function installStep14()
    {
        $db = \XF::db();
        $db->beginTransaction();

        $db->query('
            replace into xf_sv_user_registration_log (user_registration_log_id, log_date, user_id, username, ip_address, result, details, request_state)
            select log.trigger_log_id, log.log_date, log.user_id, xf_user.username, log.ip_address, log.result, log.details, log.request_state
            from xf_spam_trigger_log as log
            left join xf_user on xf_user.user_id = log.user_id
            where log.content_type = ?
        ', ['user']);

        $this->updateUserRegToJson();
        $db->commit();
    }

    public function installStep15()
    {
        $this->applyDefaultPermissions();
    }

    // Useful for testing, post-install for production
    public function installStep98()
    {
        $this->jitterCronTasks();
    }

    // Useful for testing, post-install for production
    public function installStep99()
    {
        $this->updateBrowserTimeZoneLanguages();
    }

    public function migrateLogs(array &$atomicJobs)
    {
        // the xf_sv_user_registration_log table needs to have phrases renamed from the details
        $phraseMap = [
            'aed_detectspamreg_is_banned'          => 'sv_reg_log.multi_account_is_banned',
            'aed_detectspamreg_group_membership'   => 'sv_reg_log.multi_account_group',
            'aed_detectspamreg_accept'             => 'sv_reg_log.multi_account_accept',
            'aed_detectspamreg_moderate'           => 'sv_reg_log.multi_account_moderate',
            'aed_detectspamreg_reject'             => 'sv_reg_log.multi_account_reject',
            'tpu_detectspamreg_checking'           => 'sv_reg_log.checking',
            'tpu_detectspamreg_honeypot_fail'      => 'sv_reg_log.honeypot_fail',
            'tpu_detectspamreg_honeypot_pass'      => 'sv_reg_log.honeypot_pass',
            'tpu_detectspamreg_honeypot_ok'        => 'sv_reg_log.honeypot_ok',
            'tpu_detectspamreg_sfs_ok'             => 'sv_reg_log.sfs_ok',
            'tpu_detectspamreg_sfs_fail'           => 'sv_reg_log.sfs_fail',
            'tpu_detectspamreg_country_detected'   => 'sv_reg_log.country_ok',
            'tpu_detectspamreg_country_fail'       => 'sv_reg_log.country_fail',
            'tpu_detectspamreg_country_ok'         => 'sv_reg_log.country_ok',
            'tpu_detectspamreg_as_detected'        => 'sv_reg_log.as_ok',
            'tpu_detectspamreg_as_fail'            => 'sv_reg_log.as_fail',
            'tpu_detectspamreg_as_ok'              => 'sv_reg_log.as_ok',
            'tpu_detectspamreg_tor_fail'           => 'sv_reg_log.tor_fail',
            'tpu_detectspamreg_username_fail'      => 'sv_reg_log.username_fail',
            'tpu_detectspamreg_email_fail'         => 'sv_reg_log.email_fail',
            'tpu_detectspamreg_hostname_ok'        => 'sv_reg_log.hostname_ok',
            'tpu_detectspamreg_hostname_detected'  => 'sv_reg_log.hostname_ok',
            'tpu_detectspamreg_hostname_fail'      => 'sv_reg_log.hostname_fail',
            'tpu_detectspamreg_js_fail'            => 'sv_reg_log.js_fail',
            'tpu_detectspamreg_formtimer_fail'     => 'sv_reg_log.js_timer_fail',
            'tpu_detectspamreg_formtimer_detected' => 'sv_reg_log.js_timer_ok',
            'tpu_detectspamreg_totalscore'         => 'sv_reg_log.total_score',
            'tpu_detectspamreg_port_fail'          => 'sv_reg_log.port_scan_fail',
            'tpu_detectspamreg_fail_mod'           => 'sv_reg_log.fail_score_moderated',
            'tpu_detectspamreg_fail_modposts'      => 'sv_reg_log.fail_score_posts_moderated',
            'tpu_detectspamreg_fail_rej'           => 'sv_reg_log.fail_score_rejected',
            'tpu_detectspamreg_emaillen_fail'      => 'sv_reg_log.email_length_fail',

            'Rejected. Direct rule selection triggered'            => 'sv_reg_log.direct_rule_reject',
            'Moderated. Direct rule selection triggered'           => 'sv_reg_log.direct_rule_moderated',
            'New Posts Moderated. Direct rule selection triggered' => 'sv_reg_log.direct_rule_posts_moderated',
        ];

        if ($this->db()->fetchOne("
          select trigger_log_id 
          from xf_spam_trigger_log 
          where content_type = 'user' and (details like '%tpu%' or details like '%aed%')
          limit 1
        "))
        {
            $atomicJobs[] = [
                MigrateSpamTriggerLogs::class, [
                    'phrases' => $phraseMap,
                ],
            ];
        }

        if ($this->db()->fetchOne("
          select user_registration_log_id
          from xf_sv_user_registration_log 
          where (details like '%tpu%' or details like '%aed%')
          limit 1
        "))
        {
            $atomicJobs[] = [
                MigrateUserRegLogs::class, [
                    'phrases' => $phraseMap,
                ],
            ];
        }
    }

    protected function updateUserRegToJson()
    {
        $db = \XF::db();

        $regex = '^[badiOns]:';
        $userLogs = $db->fetchAll("
          select user_registration_log_id, details, request_state 
          from xf_sv_user_registration_log 
          where details REGEXP BINARY '{$regex}' or request_state REGEXP BINARY '{$regex}'
          for update
        ");
        foreach ($userLogs as $userLog)
        {
            $details = $this->decodeJsonOrSerialized($userLog['details']) ?: [];
            $request_state = $this->decodeJsonOrSerialized($userLog['request_state']) ?: [];

            $db->query('
              update xf_sv_user_registration_log
              set details = ?, request_state = ?
              where user_registration_log_id = ?
            ', [\json_encode($details), \json_encode($request_state), $userLog['user_registration_log_id']]);
        }
    }

    public function decodeJsonOrSerialized($string)
    {
        // fastest possible check for serialized data
        if (!empty($string[1]) && $string[1] == ':' && preg_match('/^([abCdioOsS]:|N;$)/', $string))
        {
            return @\unserialize($string) ?: [];
        }
        else
        {
            return @\json_decode($string, true) ?: [];
        }
    }

    protected function mapToBasicScore($name, $sourceIsNumerical)
    {
        $option = Helper::find(OptionEntity::class, $name);
        if ($option && $option->data_type !== 'string' && !\is_array($option->option_value))
        {
            $option->data_type = 'string';
            $value = (int)$option->option_value;
            if ($sourceIsNumerical)
            {
                switch (\strval($value))
                {
                    default:
                    case '0':
                        $value = \strval($value);
                        break;
                    case '1':
                        $value = 'moderate';
                        break;
                    case '2':
                        $value = 'reject';
                        break;
                }
            }
            else
            {
                switch (\strval($value))
                {
                    default:
                        $value = 0;
                        break;
                    case 'moderate':
                        $value = 'moderate';
                        break;
                    case 'reject':
                        $value = 'reject';
                        break;
                }
            }

            $option->option_value = $value;
            $option->save();
        }
    }

    public function upgrade1000017Step1()
    {
        $this->applyDefaultPermissions();
    }

    public function upgrade1000501Step1()
    {
        $db = \XF::db();
        $db->beginTransaction();
        $this->updateUserRegToJson();
        $db->commit();
    }

    public function upgrade1040301Step1()
    {
        // attempt to fixup potentially wonky records after user merges
        \XF::db()->query('
            UPDATE xf_sv_multiple_account_log
            JOIN xf_sv_multiple_account_event ON xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
            SET xf_sv_multiple_account_log.user_id = 0
            WHERE xf_sv_multiple_account_log.user_id = xf_sv_multiple_account_event.triggering_user_id AND xf_sv_multiple_account_log.username <> \'\'
        ');

        \XF::db()->query('
            UPDATE xf_sv_multiple_account_log
            JOIN xf_sv_multiple_account_event ON xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
            SET xf_sv_multiple_account_event.triggering_user_id = 0
            WHERE xf_sv_multiple_account_log.user_id = xf_sv_multiple_account_event.triggering_user_id AND xf_sv_multiple_account_event.username <> \'\';
        ');
    }

    public function upgrade1060100Step1()
    {
        $this->db()->query("
            UPDATE xf_phrase
            SET version_id = 1000020, version_string = '1.0.0'
            WHERE addon_id = 'SV/SignupAbuseBlocking' AND (version_id >= 2000000 OR version_string LIKE '2.0.0%')
        ");
    }

    public function upgrade1060100Step2()
    {
        $this->db()->query("
            update xf_template
            set version_id = 1000020, version_string = '1.0.0'
            where addon_id = 'SV/SignupAbuseBlocking' and (version_id >= 2000000 or version_string like '2.0.0%')
        ");
    }

    public function upgrade1070002Step1()
    {
        $this->installStep1();
    }

    public function upgrade1070002Step2()
    {
        $this->installStep2();
    }

    public function upgrade1070005Step1()
    {
        $this->renameOption('svSockSignupCheckIpWhiteList', 'svSockSignupCheckIpAllowedList');
        $this->renameOption('svSignupAbuseBlocking_nonWhitelistedEmailAction', 'svSignupAbuseBlocking_nonAllowedEmailAction');
    }

    public function upgrade1070005Step2()
    {
        $this->renameOption('svLinkSpamCheckerBlackList', 'svLinkSpamCheckerRejectList');
        $this->renameOption('svLinkSpamCheckerGreyList', 'svLinkSpamCheckerModerateList');
        $this->renameOption('svLinkSpamCheckerWhiteList', 'svLinkSpamCheckerAllowedList');
    }

    public function migrateLegacyReports(array &$atomicJobs)
    {
        $hasLegacyReports = $this->db()->fetchOne('
          select report_id 
          from xf_report 
          where (content_type = ? or content_type = ?)
        ', ['alterego', 'multiple_account']);
        if ($hasLegacyReports)
        {
            $atomicJobs[] = MigrateAlterEgoDetectorReports::class;
        }
    }

    public function upgrade1110000Step1()
    {
        $this->installStep1();
    }

    public function upgrade1110200Step1()
    {
        $option = Helper::find(OptionEntity::class, 'svSignupUnknownLanguageRule');
        if ($option !== null && $option->option_value === 'moderate')
        {
            $option->option_value = '0';
            $option->save();
        }

        $option = Helper::find(OptionEntity::class, 'svSignupUnknownTimezoneRule');
        if ($option !== null && $option->option_value === 'moderate')
        {
            $option->option_value = '0';
            $option->save();
        }
    }

    public function upgrade1110200Step2()
    {
        // strip out the default `0|countryCode-Lang-countryCode` rules
        $option = Helper::find(OptionEntity::class, 'svSignupLanguageBlockingRule');
        if ($option !== null)
        {
            $rules = explode("\n", $option->option_value);
            if (!is_array($rules))
            {
                return;
            }

            foreach ($rules as $key => $rule)
            {
                $ruleParts = explode('|', $rule, 2);
                if (count($ruleParts) !== 2 || $ruleParts[0] !== '0')
                {
                    continue;
                }

                $parts = explode('-', $ruleParts[1]);
                if (count($parts) !== 3)
                {
                    continue;
                }

                if (strcasecmp($parts[0], $parts[2]) === 0)
                {
                   unset($rules[$key]);
                }
            }
            $rules = array_unique($rules);
            sort($rules);
            $value = implode("\n", $rules);
            $option->option_value = $value;

            $option->save();
        }
    }

    public function upgrade1701471175Step1(): void
    {
        $this->applyAdminPermission('svAntiSpam', 'option');

        Helper::repository(AdminPermissionRepo::class)->rebuildAdminPermissionCache();
    }

    public function upgrade1707409426Step1(): void
    {
        $torConfig = \XF::options()->svSignupTorBlockingConfig ?? [];
        if (is_array($torConfig) && ($torConfig['serverIp'] ??  '') === '1')
        {
            $torConfig['serverIp'] = '';
            Helper::repository(OptionRepo::class)->updateOption('svSignupTorBlockingConfig', $torConfig);
        }
    }

    public function upgrade1709608634Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade1710654755Step1(): void
    {
        $geoConfig = \XF::options()->svSignupGeoIPConfig ?? [];
        if (is_array($geoConfig) && ($geoConfig['maxMind'] ??  '') === '')
        {
            $geoConfig['maxMind'] = 1;
            Helper::repository(OptionRepo::class)->updateOption('svSignupGeoIPConfig', $geoConfig);
        }
    }

    public function upgrade1710655194Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade1710654755Step2(): void
    {
        $asnConfig = \XF::options()->svSignupAsnBlockingConfig ?? [];
        if (is_array($asnConfig) && ($asnConfig['maxMind'] ??  '') === '')
        {
            $asnConfig['maxMind'] = 1;
            Helper::repository(OptionRepo::class)->updateOption('svSignupAsnBlockingConfig', $asnConfig);
        }
    }

    public function upgrade1717093762Step1(): void
    {
        $options = Helper::finder(OptionFinder::class)->whereId([
            'svAsnCheckActionOnNotMatch',
            'svGeoIpCheckActionOnNotMatch',
            'svLanguageCheckActionOnNotMatch',
            'svTimezoneCheckActionOnNotMatch',
        ])->fetch();

        /** @var OptionEntity $option */
        foreach($options as $option)
        {
            if ($option->option_value === 'report')
            {
                $option->option_value = 'moderated';
                $option->save();
            }
        }

        \XF::triggerRunOnce();
    }

    public function postInstall(array &$stateChanges)
    {
        parent::postInstall($stateChanges);
        $atomicJobs = [];
        $this->setupSockCheckReportingUser();
        $this->migrateLegacyReports($atomicJobs);
        $this->migrateLogs($atomicJobs);
        $this->updateBrowserTimeZoneLanguages();
        $this->setupIpIntelDbl(true);
        $this->updateMaxMindDb();
        $atomicJobs[] = MigrateLoginRecords::class;

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'signup-abuse-blocking-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    /**
     * @param       $previousVersion
     * @param array $stateChanges
     */
    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);
        $atomicJobs = [];

        $this->setupSockCheckReportingUser();
        if ($previousVersion < 1000016)
        {
            $option = Helper::find(OptionEntity::class, 'svSignupAsnBlockingRule');
            if ($option && $option->default_value && !$option->option_value)
            {
                $option->option_value = $option->default_value;
            }
        }

        if ($previousVersion < 1000501)
        {
            $this->migrateLegacyReports($atomicJobs);
        }

        if ($previousVersion < 1030000)
        {
            $this->migrateLogs($atomicJobs);
        }

        if ($previousVersion && $previousVersion < 1000501)
        {
            $atomicJobs[] = Upgrade1000500Step1::class;
            $atomicJobs[] = Upgrade1000500Step2::class;
        }

        if ($previousVersion < 1110000)
        {
            $this->updateBrowserTimeZoneLanguages();
        }

        if ($previousVersion < 1702623682)
        {
            $this->setupIpIntelDbl(false);
        }

        if ($previousVersion < 1708600159)
        {
            $atomicJobs[] = LinkUsersToMultiAccountReport::class;
            $atomicJobs[] = RebuildMultiAccountSummary::class;
        }

        if ($previousVersion < 1710448788)
        {
            $this->updateMaxMindDb();
            $atomicJobs[] = MigrateLoginRecords::class;
        }

        if ($previousVersion < 1715586733)
        {
            $this->jitterCronTasks();
        }

        if ($this->applyDefaultPermissions($previousVersion))
        {
            $atomicJobs[] = PermissionRebuildJob::class;
        }

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'signup-abuse-blocking-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    public function postRebuild()
    {
        parent::postRebuild();
        $this->setupSockCheckReportingUser();
    }

    protected function setupSockCheckReportingUser(): void
    {
        $rawUserId = \XF::options()->svSockSignupCheckReportingUser ?? 0;
        /** @noinspection PhpCastIsUnnecessaryInspection */
        $userId = (int)$rawUserId;
        if ($userId === 0)
        {
            \XF::logError("[SignupAbuseBlocking] 'Multi-account report user' has an invalid userId:{$rawUserId}", true);
        }
        $user = Helper::find(UserEntity::class, $userId);
        if ($user === null)
        {
            \XF::logError("[SignupAbuseBlocking] 'Multi-account report user' userId:{$userId} does not exist", true);

            $username = (string)\XF::db()->fetchOne('
                SELECT username
                FROM xf_user
                ORDER BY user_id
                LIMIT 1
            ');

            if ($username === '')
            {
                \XF::logError("[SignupAbuseBlocking] Unable to find a valid user for 'Multi-account report user' option", true);
            }

            Helper::repository(OptionRepo::class)->updateOption('svSockSignupCheckReportingUser', $username);
        }
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Drops columns from core tables.
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function uninstallStep3(): void
    {
        $this->app->registry()->delete([
            AllowEmailDomain::ALLOWED_EMAIL_DOMAINS_CACHE_KEY,
            //'svSAB_a_e_d',
            'svSAB_b_e_d',
        ]);
    }

    protected function applyDefaultPermissions(int $previousVersion = 0): bool
    {
        $applied = false;

        if (!$previousVersion)
        {
            $this->applyGlobalPermissionByGroup('multipleAccountHandling', 'viewreport', [UserEntity::GROUP_MOD, UserEntity::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('multipleAccountHandling', 'logAlerting', [UserEntity::GROUP_MOD, UserEntity::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('multipleAccountHandling', 'userAlerting', [UserEntity::GROUP_ADMIN]);
            $this->applyAdminPermission('svAntiSpam', 'option');
            $applied = true;
        }

        if ($previousVersion < 1701471175)
        {
            $this->applyGlobalPermissionByGroup('general', 'svSignup_approve_email', [UserEntity::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('general', 'svSignup_ban_asn', [UserEntity::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('general', 'svSignup_ban_email', [UserEntity::GROUP_ADMIN]);
        }

        return $applied;
    }

    protected function updateMaxMindDb(): void
    {
        $this->jitterCronTasks();

        $keyDuringInstall = '';

        try
        {
            MaxMindRepo::get()->updateGeoIpDb($keyDuringInstall);
            MaxMindRepo::get()->updateAsnDb($keyDuringInstall);
        }
        catch (\Throwable $e)
        {
            //\XF::logException($e);
        }
    }

    protected function jitterCronTasks(): void
    {
        $cronTasks = Helper::finder(CronEntryFinder::class)
                        ->whereId([
                            'svSAB_GeoIP',
                            'svSAB_Asn',
                        ])
                        ->fetch();
        foreach ($cronTasks as $cron)
        {
            /** @var CronEntryEntity $cron */
            $rules = $cron->run_rules;
            $rules['minutes'] = [random_int(0, 59)];
            $rules['hours'] = [random_int(0, 23)];
            $cron->run_rules = $rules;
            $cron->saveIfChanged();
        }
    }


    protected function updateBrowserTimeZoneLanguages(): void
    {
        Helper::repository(OptionRepo::class)->updateOptions([
            'svSignupTimezoneBlockingRule' => TimezoneList::buildList(),
            'svSignupLanguageBlockingRule' => LanguageList::buildList(),
        ]);
    }

    protected function setupIpIntelDbl(bool $enable): void
    {
        $blob = \XF::options()->svSignupGetIpIntelRule ?? null;
        if (!is_array($blob) || !array_key_exists('email', $blob))
        {
            return;
        }

        if ($blob['email'] === '')
        {
            $blob['email'] = \XF::options()->contactEmailAddress;
            if ($enable)
            {
                $blob['enable'] = 1;
            }

            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app()->validator('Email');
            $emailValidator->setOptions([
                'check_typos' => true, // avoid changing email we set
            ]);
            if ($emailValidator->isValid($blob['email']))
            {
                $blob['enable'] = 0;
            }

            Helper::repository(OptionRepo::class)->updateOption('svSignupGetIpIntelRule', $blob);
        }
    }

    protected function getTables(): array
    {
        $tables = [];

        $tables['xf_sv_multiple_account_report_data'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'report_data_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'log_count', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'active', 'tinyint', 1)->nullable(true)->setDefault(1);
            $this->addOrChangeColumn($table, 'report_id', 'int')->nullable(true)->setDefault(null);

            $table->addUniqueKey(['report_id', 'active'], 'report');
        };

        $tables['xf_sv_multiple_account_report_data_user'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'report_data_id', 'int');
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'first_seen_date', 'int');
            $this->addOrChangeColumn($table, 'last_seen_date', 'int');
            $this->addOrChangeColumn($table, 'count', 'int');

            $table->addPrimaryKey(['report_data_id', 'user_id']);
            $table->addKey('user_id');
        };

        $tables['xf_sv_multiple_account_user'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'user_id', 'int')->primaryKey();
            $this->addOrChangeColumn($table, 'first_seen_date', 'int');
            $this->addOrChangeColumn($table, 'last_seen_date', 'int');
            $this->addOrChangeColumn($table, 'count', 'int');

            $table->addKey(['last_seen_date', 'user_id']);
        };

        $tables['xf_sv_multiple_account_event'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'event_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'detection_date', 'int');
            $this->addOrChangeColumn($table, 'report_data_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'triggering_user_id', 'int');
            $this->addOrChangeColumn($table, 'username', 'varchar', 50)->setDefault('');
            $this->addOrChangeColumn($table, 'detection_action', 'varchar', 50);
            $this->addOrChangeColumn($table, 'log_count', 'int')->setDefault(0);

            $table->addPrimaryKey('event_id');
            $table->addKey(['triggering_user_id', 'detection_date']);
            $table->addKey('report_data_id');
        };

        $tables['xf_sv_multiple_account_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'log_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'event_id', 'int');
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'username', 'varchar', 50)->setDefault('');
            $this->addOrChangeColumn($table, 'detection_date', 'int');
            $this->addOrChangeColumn($table, 'detection_methods', 'blob');
            $this->addOrChangeColumn($table, 'token_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'is_alertable', 'tinyint', 1);
            $this->addOrChangeColumn($table, 'active', 'tinyint', 1);

            $table->addKey(['event_id']);
            $table->addKey(['detection_date', 'user_id']);
        };

        $tables['xf_sv_multiple_account_token'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'token_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'token', 'varchar', 32);
            $this->addOrChangeColumn($table, 'token_date', 'int');
            $this->addOrChangeColumn($table, 'active', 'tinyint', 1)->nullable(true)->setDefault(null);

            $table->addUniqueKey('token');
            $table->addKey(['user_id', 'token_date']);
            $table->addUniqueKey(['user_id', 'active'], 'active');
        };

        // copy of the spam trigger log table with sane expiry policy
        $tables['xf_sv_user_registration_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'user_registration_log_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'log_date', 'int');
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'username', 'varchar', 100)->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'ip_address', 'varbinary', 16);
            $this->addOrChangeColumn($table, 'result', 'varbinary', 25);
            $this->addOrChangeColumn($table, 'details', 'mediumblob');
            $this->addOrChangeColumn($table, 'request_state', 'mediumblob');
            $this->addOrChangeColumn($table, 'asn', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'country', 'varchar', 3)->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'browser_language', 'mediumblob')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'timezone', 'varchar', 50)->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'user_agent_id', 'int')->nullable()->setDefault(null);

            $table->addKey('user_id');
            $table->addKey('log_date');
            $table->addKey(['ip_address', 'log_date']);
        };

        $tables['xf_sv_signup_abuse_blocking_allow_email_domain'] = function ($table)
        {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'email_domain', 'varchar', 120);
            $this->addOrChangeColumn($table, 'create_user_id', 'int');
            $this->addOrChangeColumn($table, 'create_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'reason', 'varchar', 255)->setDefault('');
            $this->addOrChangeColumn($table, 'last_triggered_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'trigger_count', 'int')->setDefault(0);

            $table->addPrimaryKey('email_domain');
        };

        $tables['xf_sv_signup_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'signup_log_id', 'bigint')->autoIncrement();
            $this->addOrChangeColumn($table, 'ip_address', 'varbinary', 16);
            $this->addOrChangeColumn($table, 'asn', 'int')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'country', 'varchar', 3)->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'user_agent_id', 'int')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'log_date', 'int');

            $table->addKey(['log_date']);
            $table->addKey(['ip_address', 'log_date']);
            $table->addKey(['asn', 'log_date']);
            $table->addKey(['country', 'log_date']);

            if (\XF::isAddOnActive('XenCentral/MultiSite'))
            {
                $this->addOrChangeColumn($table, 'domain', 'varchar', 40)->nullable()->setDefault(null);
            }
        };

        // xf_sv_login_log should be a super-set of xf_sv_signup_log. see logSignupRecordForRateLimit
        $tables['xf_sv_login_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'login_log_id', 'bigint')->autoIncrement();
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'ip_address', 'varbinary', 16);
            $this->addOrChangeColumn($table, 'action', 'varbinary', 25);
            $this->addOrChangeColumn($table, 'asn', 'int')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'country', 'varchar', 3)->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'user_agent_id', 'int')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'log_date', 'int');

            $table->addKey(['log_date']);
            $table->addKey(['user_id', 'log_date']);
            $table->addKey(['ip_address', 'log_date']);
            $table->addKey(['asn', 'log_date']);
            $table->addKey(['country', 'log_date']);
            $table->addKey(['action', 'log_date']);

            if (\XF::isAddOnActive('XenCentral/MultiSite'))
            {
                $this->addOrChangeColumn($table, 'domain', 'varchar', 40)->nullable()->setDefault(null);
            }
        };

        $tables['xf_sv_user_agent'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'user_agent_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'user_agent', 'blob');
            $this->addOrChangeColumn($table, 'user_agent_hash', 'varbinary', 32);

            $this->addOrChangeColumn($table, 'first_log_date', 'int');
            $this->addOrChangeColumn($table, 'last_log_date', 'int');
            $this->addOrChangeColumn($table, 'count', 'int');

            $table->addKey('first_log_date');
            $table->addKey('last_log_date');
        };

        $tables['xf_sv_signup_throttle_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'signup_throttle_log_id', 'bigint')->autoIncrement();
            $this->addOrChangeColumn($table, 'ip_address', 'varbinary', 16)->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'asn', 'int')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'country', 'varchar', 3)->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'log_date', 'int');
            $this->addOrChangeColumn($table, 'expiry_date', 'int');
            $this->addOrChangeColumn($table, 'signup_attempts', 'int');

            if (\XF::isAddOnActive('XenCentral/MultiSite'))
            {
                $this->addOrChangeColumn($table, 'domain', 'varchar', 40)->nullable()->setDefault(null);
            }

            $table->addKey(['expiry_date']);
            $table->addKey(['ip_address', 'expiry_date']);
            $table->addKey(['asn', 'expiry_date']);
            $table->addKey(['country', 'expiry_date']);
        };

        return $tables;
    }

    protected function getAlterTables(): array
    {
        $tables = [];

        $tables['xf_user_profile'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'multiple_account_detection_alertable', 'tinyint', 1)->nullable(true)->setDefault(1);
        };

        return $tables;
    }

    protected function getRemoveAlterTables(): array
    {
        $tables = [];

        $tables['xf_user_profile'] = function (Alter $table) {
            $table->dropColumns('multiple_account_detection_alertable');
        };

        return $tables;
    }

    protected function populateOptionOrDefault(array &$config, string $existingName, string $name, $default = null)
    {
        if (isset($config[$existingName]))
        {
            return;
        }

        if (\XF::options()->offsetExists($name))
        {
            $config[$existingName] = \XF::options()->offsetGet($name);
        }
        else
        {
            $config[$existingName] = $default;
        }
    }

    protected function getOptionOrDefault(string $name, $default = null)
    {
        if (\XF::options()->offsetExists($name))
        {
            return \XF::options()->offsetGet($name);
        }

        return $default;
    }

    protected function cloneOption(string $old, string $new)
    {
        $optionOld = Helper::find(OptionEntity::class, $old);
        $optionNew = Helper::find(OptionEntity::class, $new);
        if ($optionOld && !$optionNew)
        {
            $optionNew = Helper::createEntity(OptionEntity::class);
            $optionNew->setOption('verify_validation_callback', false);
            $optionNew->setOption('verify_value', false);
            $optionNew->option_id = $new;
            $optionNew->addon_id = $this->addOn->getAddOnId();
            $optionNew->edit_format = $optionOld->edit_format;
            $optionNew->edit_format_params = $optionOld->edit_format_params;
            $optionNew->data_type = $optionOld->data_type;
            $optionNew->sub_options = $optionOld->sub_options;
            $optionNew->validation_class = $optionOld->validation_class;
            $optionNew->validation_method = $optionOld->validation_method;

            // don't bother with default value, add-on install will reconfigure it
            //$optionNew->default_value = $optionOld->default_value;
            // set the value after configuration
            $optionNew->option_value = $optionOld->option_value;

            $optionNew->save();
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
