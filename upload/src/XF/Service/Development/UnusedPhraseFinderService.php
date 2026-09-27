<?php

namespace XF\Service\Development;

use XF\App;
use XF\Entity\AddOn;
use XF\Finder\PhraseMapFinder;
use XF\Service\AbstractService;

class UnusedPhraseFinderService extends AbstractService
{
	protected $addOn;

	public function __construct(App $app, AddOn $addOn)
	{
		parent::__construct($app);

		$this->addOn = $addOn;
	}

	public function findUnused(): array
	{
		$results = $this->findAll();
		return $results['unused'];
	}

	public function findAll(): array
	{
		$existingAddonTitles = $this->getExistingPhraseTitles(false);
		$existingAllTitles = $this->getExistingPhraseTitles(true);

		$usedInTemplates = $this->getPhrasesUsedInTemplates();
		$usedInFiles = $this->getPhrasesContainedInFiles();
		$usedInOptsAndProps = $this->getPhrasesUsedInOptionsOrProps();
		$usedInContentTypes = $this->getPhrasesFromContentTypes();
		$used = array_values(array_unique(array_merge(
			$usedInTemplates,
			$usedInFiles,
			$usedInOptsAndProps,
			$usedInContentTypes
		)));

		$unused = array_diff($existingAddonTitles, $used);
		sort($unused);

		$unknown = array_diff($used, $existingAllTitles);
		$unknown = array_diff($unknown, $this->getProtectedUnknownPhrases($this->addOn->addon_id));
		sort($unknown);

		return [
			'unused' => $unused,
			'unknown' => $unknown,
		];
	}

	protected function getExistingPhraseTitles(bool $includeXf = true): array
	{
		$addOn = $this->addOn;
		$addOnIds = [$addOn->addon_id];
		if ($includeXf && $addOn->addon_id !== 'XF')
		{
			$addOnIds[] = 'XF';
		}

		$protectedPhrases = $this->getProtectedPhrases($addOn->addon_id);

		$finder = $this->finder(PhraseMapFinder::class)
			->with('Phrase')
			->where('language_id', 0)
			->where('phrase_group', '=', null)
			->where('Phrase.addon_id', $addOnIds)
			->order('title');

		if ($protectedPhrases)
		{
			$finder->where('title', '!=', $protectedPhrases);
		}

		$existingPhrases = $finder->fetch();

		return $existingPhrases->pluckNamed('title');
	}

	protected function getPhrasesUsedInTemplates(): array
	{
		$usedInTemplates = $this->db()->fetchAllColumn('
			SELECT tp.phrase_title
			FROM xf_template_phrase AS tp
			INNER JOIN xf_template AS t ON
				(tp.template_id = t.template_id)
			INNER JOIN xf_phrase AS phrase ON
				(phrase.title = tp.phrase_title)
			WHERE t.style_id = 0
				AND phrase.addon_id = ?
			ORDER BY CONVERT(phrase_title USING utf8)
		', $this->addOn->addon_id);

		return array_unique($usedInTemplates);
	}

	protected function getAddOnFileRoot(): string
	{
		if ($this->addOn->addon_id == 'XF')
		{
			return \XF::getSourceDirectory() . '/XF';
		}
		else
		{
			return \XF::getAddOnDirectory() . '/' . $this->addOn->addon_id;
		}
	}

	protected function getPhrasesContainedInFiles(): array
	{
		$phrases = [];

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getAddOnFileRoot()));
		while ($iterator->valid())
		{
			if ($iterator->isFile() && substr($iterator->getFilename(), -4) == '.php')
			{
				if (!is_readable($iterator->key()))
				{
					$iterator->next();
					continue;
				}

				$contents = file_get_contents($iterator->key());
				if ($contents === false)
				{
					$iterator->next();
					continue;
				}

				if (preg_match_all('/XF::(?>phrase\(|phraseDeferred\(|phrasedException\()\s*(?>\'|")([^\'"\s]+)(?>\'|")\s*[),]/', $contents, $matches, PREG_SET_ORDER))
				{
					foreach ($matches AS $match)
					{
						$phrases[] = $match[1];
					}
				}

				if (strpos($iterator->getSubPathName(), 'Entity') === 0)
				{
					if (preg_match_all('/\'(?>required|unique)\'\s+=>\s+(?>\'|")([^\'"\s]+)(?>\'|")\s*[\],]/', $contents, $matches, PREG_SET_ORDER))
					{
						foreach ($matches AS $match)
						{
							$phrases[] = $match[1];
						}
					}
					if (preg_match_all('/\'(?>match)\'\s+=>\s+(?>\[)\s+(?>\'|")(?>[^\'"\s]+)(?>\'|"),\s+(?>\'|")([^\'"\s]+)(?>\'|")\s*[\],]/', $contents, $matches, PREG_SET_ORDER))
					{
						foreach ($matches AS $match)
						{
							$phrases[] = $match[1];
						}
					}
				}
				else if (strpos($iterator->getSubPathName(), 'Data') === 0 || strpos($iterator->getSubPathName(), 'Spam') === 0)
				{
					if (preg_match_all('/\'phrase\'\s+=>\s+(?>\'|")([^\'"\s]+)(?>\'|")\s*[\],\s]/', $contents, $matches, PREG_SET_ORDER))
					{
						foreach ($matches AS $match)
						{
							$phrases[] = $match[1];
						}
					}
				}
			}

			$iterator->next();
		}

		foreach ($phrases AS &$phrase)
		{
			$phrase = preg_replace('/^[^a-z0-9_]+/i', '', $phrase);
			$phrase = preg_replace('/[^a-z0-9_]+$/i', '', $phrase);
		}

		return array_unique($phrases);
	}

	public function getPhrasesUsedInOptionsOrProps(): array
	{
		$phrases = [];

		$options = $this->db()->fetchAllColumn("
			SELECT edit_format_params
			FROM xf_option
			WHERE edit_format_params LIKE '%{{ phrase(%'
				AND addon_id = ?
		", $this->addOn->addon_id);

		$props = $this->db()->fetchAllColumn("
			SELECT value_parameters
			FROM xf_style_property
			WHERE value_parameters LIKE '%{{ phrase(%'
				AND addon_id = ?
		", $this->addOn->addon_id);


		foreach (array_merge($options, $props) AS $phrasedParam)
		{
			if (preg_match_all('/{{ phrase\(\'([^\'"]+)\'/i', $phrasedParam, $matches, PREG_SET_ORDER))
			{
				foreach ($matches AS $match)
				{
					$phrase = $match[1];
					$phrase = preg_replace('/^[^a-z0-9_]+/i', '', $phrase);
					$phrase = preg_replace('/[^a-z0-9_]+$/i', '', $phrase);

					$phrases[] = $phrase;
				}
			}
		}

		return array_unique($phrases);
	}

	public function getPhrasesFromContentTypes(): array
	{
		$contentTypes = $this->db()->fetchAllColumn('
			SELECT DISTINCT(content_type)
			FROM xf_content_type_field
			WHERE addon_id = ?
		', $this->addOn->addon_id);

		$phrases = [];

		foreach ($contentTypes AS $contentType)
		{
			$phrases[] = $this->app->getContentTypePhraseName($contentType, false);
			$phrases[] = $this->app->getContentTypePhraseName($contentType, true);
		}

		return array_unique($phrases);
	}

	/**
	 * An array of phrases that are difficult to detect, or otherwise should be protected.
	 *
	 * The array is keyed by add-on ID if no $addOnId specified.
	 *
	 * @param string|null $addOnId
	 *
	 * @return array
	 */
	protected function getProtectedPhrases(?string $addOnId = null): array
	{
		$protected = [
			'XF' => [
				// date/time
				'1_day_ago',
				'1_week_ago',
				'2_weeks_ago',
				'1_month_ago',
				'3_months_ago',
				'6_months_ago',
				'9_months_ago',
				'1_year_ago',
				'2_years_ago',
				'month',
				'month_1_short',
				'month_2_short',
				'month_3_short',
				'month_4_short',
				'month_5_short',
				'month_6_short',
				'month_7_short',
				'month_8_short',
				'month_9_short',
				'month_10_short',
				'month_11_short',
				'month_12_short',
				'time_am_lower',
				'time_am_upper',
				'time_pm_lower',
				'time_pm_upper',

				// number/file size units
				'x_b',
				'x_m',
				'x_k',
				'x_tb',
				'x_gb',
				'x_mb',
				'x_kb',
				'x_bytes',

				// cost phrases
				'x_per_y_days',
				'x_per_y_months',
				'x_per_y_years',
				'x_for_y_days',
				'x_for_y_months',
				'x_for_y_years',
				'x_per_day',
				'x_per_month',
				'x_per_year',
				'x_for_one_day',
				'x_for_one_month',
				'x_for_one_year',

				// email stop phrases
				'are_you_sure_you_want_to_stop_emails_for_one_thread',
				'are_you_sure_you_want_to_stop_emails_for_all_threads',
				'are_you_sure_you_want_to_stop_emails_for_one_forum',
				'are_you_sure_you_want_to_stop_emails_for_all_forums',

				// user change log
				'accepted_privacy_policy',
				'accepted_terms_rules',
				'avatar_date',
				'dob_day',
				'dob_month',
				'dob_year',
				'email_direct_message_notifications',
				'gravatar',
				'push_direct_message_notifications',
				'two_step_verification_enabled',

				// spam checker
				'akismet_matched',
				'dnsbl_matched',
				'sfs_matched_x',
				'shared_ip_banned_user_x',
				'shared_ip_rejected_user_x',
				'spam_phrase_matched_x',

				// in the files but not directly found (or not within src/XF)
				'a_z_0_9_and_only',
				'connected_account_provider_specified_cannot_be_found',
				'drop_down_selection',
				'multiple_choice_drop_down_selection',
				'multi_line_text_box',
				'new_alert_at_x',
				'notification_from_x',
				'please_enter_valid_facebook_username_using_alphanumeric_dot_numbers',
				'requested_log_entry_not_found',
				'single_line_text_box',
				'star_rating',
				'title_page_x',
				'unsubscribe_from_mailing_list',
				'you_have_new_notification_at_x',

				// generic phrases that add-ons may use
				'all_categories',
				'anonymous',
				'avatars',
				'characters',
				'created_between',
				'data_directory',
				'deleted_member',
				'download',
				'email_address',
				'inbox',
				'internal_data_directory',
				'leave_rating',
				'log_in_or_register_now',
				'sort_categories',
				'submit_rating',
				'x_years',

				// used by XFI
				'directory_specified_as_x_y_not_found_is_not_readable',
				'directory_x_does_not_contain_expected_contents',
				'directory_x_not_found_is_not_readable',
				'following_and_ignored_users',
				'imported_content',
				'mysql_database_name',
				'mysql_force_charset',
				'mysql_force_charset_explain',
				'mysql_password',
				'mysql_port',
				'mysql_server',
				'mysql_table_prefix',
				'mysql_user_name',
				'please_enter_database_name',
				'post_edit_history',
				'source_database_configuration',
				'source_database_connection_details_not_correct_x',
				'table_prefix_incorrect_try_x',
				'table_prefix_or_database_name_is_not_correct',
				'thread_polls',
				'user_name_changed_to_x',
				'you_may_only_import_from_xenforo_20',

				// somewhat legacy stuff, but worth keeping around in case in use by add-ons
				'comment_date',
				'confirm_password',

				// shared category stuff (mostly shared between XFRM and XFMG)
				'category',
				'categories',
				'disable_sub_category_notifications',
				'enable_sub_category_notifications',
				'include_notifications_for_content_in_sub_categories',
				'stop_notification_emails_from_all_categories',
				'sub_categories',
				'watch_category',
				'watched_categories',
				'unwatch_category',
				'you_not_watching_any_categories',
				'you_sure_you_want_to_unwatch_this_category',
			],
		];

		if ($addOnId)
		{
			return $protected[$addOnId] ?? [];
		}
		else
		{
			return $protected;
		}
	}

	protected function getProtectedUnknownPhrases(?string $addOnId = null): array
	{
		$protected = [
			'XF' => [
				'button.cancel',
				'button.delete',
				'button.preview',
				'code_language.general',
				'code_language.rich',
				'likes.deleted_user',
				'option_explain.preventDiscouragedRegistration',
				'smilie_category_title.uncategorized',
				'tfa.backup',
			],
		];

		if ($addOnId)
		{
			return $protected[$addOnId] ?? [];
		}
		else
		{
			return $protected;
		}
	}
}
