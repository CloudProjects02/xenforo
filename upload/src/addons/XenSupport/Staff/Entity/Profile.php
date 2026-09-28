<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS - Free (v1.x)
 * @property int $user_id
 * @property string $quote
 * @property string $social_discord
 * @property string $social_twitter
 * @property string $social_twitch
 * @property string $social_youtube
 * @property string $social_instagram
 * @property string $social_github
 * @property string $social_website
 * @property string $accent_color
 * @property int $thanks_count
 * @property int $last_edit_date
 *
 * COLUMNS - Pro (v2.x)
 * @property string $rich_bio
 * @property string $bio_format
 * @property string $timezone
 * @property bool   $allow_contact
 * @property bool   $allow_anonymous_contact
 * @property string $online_override
 * @property int    $badge_id
 * @property int    $total_responses
 * @property int    $total_response_seconds
 * @property int    $contact_received_count
 *
 * GETTERS
 * @property float $avg_response_minutes
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XenSupport\Staff\Entity\Badge|null $Badge
 */
class Profile extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_profile';
		$structure->shortName = 'XenSupport\Staff:Profile';
		$structure->primaryKey = 'user_id';
		$structure->columns = [
			'user_id'           => ['type' => self::UINT, 'required' => true],
			'quote'             => ['type' => self::STR, 'default' => '', 'maxLength' => 280],
			'social_discord'    => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_twitter'    => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_twitch'     => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_youtube'    => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_instagram'  => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_github'     => ['type' => self::STR, 'default' => '', 'maxLength' => 100],
			'social_website'    => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'accent_color'      => ['type' => self::STR, 'default' => '', 'maxLength' => 20],
			'thanks_count'      => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'last_edit_date'    => ['type' => self::UINT, 'default' => 0],

			// Pro columns (v2.x)
			'rich_bio'                  => ['type' => self::STR, 'default' => ''],
			'bio_format'                => ['type' => self::STR, 'default' => 'bbcode', 'allowedValues' => ['bbcode', 'html']],
			'timezone'                  => ['type' => self::STR, 'default' => 'UTC', 'maxLength' => 50],
			'allow_contact'             => ['type' => self::BOOL, 'default' => true],
			'allow_anonymous_contact'   => ['type' => self::BOOL, 'default' => true],
			'online_override'           => ['type' => self::STR, 'default' => 'auto', 'allowedValues' => ['auto', 'online', 'busy', 'away', 'offline']],
			'badge_id'                  => ['type' => self::UINT, 'default' => 0],
			'total_responses'           => ['type' => self::UINT, 'default' => 0],
			'total_response_seconds'    => ['type' => self::UINT, 'default' => 0],
			'contact_received_count'    => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = ['avg_response_minutes' => true];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'Badge' => [
				'entity' => 'XenSupport\Staff:Badge',
				'type' => self::TO_ONE,
				'conditions' => 'badge_id',
				'primary' => true,
			],
		];
		return $structure;
	}

	public function getAvgResponseMinutes(): float
	{
		if (!$this->total_responses) return 0.0;
		return round(($this->total_response_seconds / $this->total_responses) / 60, 1);
	}

	public function getSocialLinks(): array
	{
		$out = [];
		if ($this->social_discord)   { $out['discord']   = ['icon' => 'fab fa-discord',     'value' => $this->social_discord,   'link' => '']; }
		if ($this->social_twitter)   { $out['twitter']   = ['icon' => 'fab fa-twitter',     'value' => $this->social_twitter,   'link' => 'https://x.com/' . ltrim($this->social_twitter, '@')]; }
		if ($this->social_twitch)    { $out['twitch']    = ['icon' => 'fab fa-twitch',      'value' => $this->social_twitch,    'link' => 'https://twitch.tv/' . $this->social_twitch]; }
		if ($this->social_youtube)   { $out['youtube']   = ['icon' => 'fab fa-youtube',     'value' => $this->social_youtube,   'link' => $this->youtubeLink()]; }
		if ($this->social_instagram) { $out['instagram'] = ['icon' => 'fab fa-instagram',   'value' => $this->social_instagram, 'link' => 'https://instagram.com/' . ltrim($this->social_instagram, '@')]; }
		if ($this->social_github)    { $out['github']    = ['icon' => 'fab fa-github',      'value' => $this->social_github,    'link' => 'https://github.com/' . $this->social_github]; }
		if ($this->social_website)   { $out['website']   = ['icon' => 'fa-globe',           'value' => $this->social_website,   'link' => $this->social_website]; }
		return $out;
	}

	protected function discordLink(): string
	{
		// Discord IDs are not directly linkable; show as a copyable tag (no href)
		return '';
	}

	protected function youtubeLink(): string
	{
		$v = $this->social_youtube;
		if (strpos($v, 'http') === 0) return $v;
		if (strpos($v, '@') === 0)   return 'https://youtube.com/' . $v;
		return 'https://youtube.com/@' . $v;
	}

	protected function _preSave()
	{
		if ($this->isChanged(['quote', 'social_discord', 'social_twitter', 'social_twitch', 'social_youtube', 'social_instagram', 'social_github', 'social_website', 'accent_color', 'rich_bio']))
		{
			$this->last_edit_date = \XF::$time;
		}

		if ($this->accent_color !== '')
		{
			$color = trim($this->accent_color);
			if ($color && $color[0] !== '#')
			{
				$color = '#' . $color;
			}
			if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color))
			{
				$color = '';
			}
			$this->accent_color = $color;
		}

		foreach (['social_website'] AS $f)
		{
			$v = trim((string) $this->{$f});
			if ($v && !preg_match('#^https?://#i', $v))
			{
				$v = 'https://' . $v;
			}
			$this->{$f} = $v;
		}
	}
}
