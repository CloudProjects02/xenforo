<?php

namespace BS\RealTimeChat\Template;

class Templater
{
    public const DEFAULT_ROOM_ICON = 'fa-bullhorn';

    public function fnTheme(
        \XF\Template\Templater $templater,
        &$escape,
        int $index
    ) {
        $escape = false;

        $style = $this->assertStyle($templater);

        $themes = $style->getProperty('rtcThemes');
        $themes = array_values(array_filter($themes));

        if ($index === -1) {
            $index = (int)$style->getProperty('rtcDefaultThemeIndex');
        }

        if (! isset($themes[$index])) {
            $index = 0;
        }

        $settings = $themes[$index];

        if (empty($settings)) {
            $settings = reset($themes) ?? [];
        }

        $backgroundColorKeys = [
            'top_left',
            'top_right',
            'bottom_left',
            'bottom_right'
        ];
        foreach ($backgroundColorKeys as $colorSetting) {
            if (isset($settings[$colorSetting])) {
                $settings[$colorSetting] = $this->parseLessColor($templater, $settings[$colorSetting]);
            }
        }
        $settings['background_colors'] = [
            $settings['bottom_right'],
            $settings['bottom_left'],
            $settings['top_left'],
            $settings['top_right'],
        ];

        unset($settings['bottom_right'], $settings['bottom_left'], $settings['top_left'], $settings['top_right']);

        // if pattern not external link, make it absolute
        if (! preg_match('/^https?:\/\//', $settings['pattern'])) {
            $settings['pattern'] = $templater->func('base_url', [$settings['pattern'], true]);
        }

        return $settings;
    }

    public function fnRoomTheme(
        \XF\Template\Templater $templater,
        &$escape,
        $options,
        bool $fresh = false
    ) {
        $escape = false;

        $style = $this->assertStyle($templater);

        $themeIndex = is_int($options)
            ? $options
            : ($options['wallpaper']['options']['theme_index'] ?? -1);

        if ($themeIndex === -1) {
            $themeIndex = (int)$style->getProperty('rtcDefaultThemeIndex');
        }

        if ($fresh) {
            return $this->buildRoomTheme($templater, $themeIndex);
        }

        $themesCache = \XF::app()->container('rtcThemes');
        $theme = $themesCache[$style->getId()][$themeIndex]
            ?? $this->buildRoomTheme($templater, $themeIndex);

        if (! empty($options['wallpaper'])) {
            $cssVars = [];

            $this->buildWallpaperCssVars($cssVars, $options['wallpaper']);

            $theme['css'] .= $this->buildCssVars($cssVars);
        }

        return $theme;
    }

    protected function buildRoomTheme(\XF\Template\Templater $templater, $options): array
    {
        if (is_int($options)) {
            $options = [
                'wallpaper' => [
                    'url'     => '',
                    'type'    => 'default',
                    'options' => [
                        'theme_index' => $options
                    ]
                ]
            ];
        }

        $wallpaper = $options['wallpaper'] ?? [
            'url'     => '',
            'type'    => 'default',
            'options' => [
                'blurred'     => false,
                'theme_index' => -1
            ]
        ];

        $cssVars = [];
        $theme = $templater->func('rtc_theme', [$wallpaper['options']['theme_index']]);

        $this->buildWallpaperCssVars($cssVars, $wallpaper);
        $this->buildColorVars($cssVars, $theme, $templater);
        $this->buildBubbleCssVars($cssVars, $theme, $templater);
        $this->buildVisitorBubbleCssVars($cssVars, $theme, $templater);

        return [
            'key'    => $wallpaper['options']['theme_index'],
            'config' => $theme,
            'css'    => $this->buildCssVars($cssVars),
        ];
    }

    protected function buildCssVars(array $cssVars): string
    {
        // map css vars key to add -- prefix
        $cssVars = array_combine(
            array_map(static function ($key) {
                return '--'.$key;
            }, array_keys($cssVars)),
            array_values($cssVars)
        );

        // map css vars to css prop strings
        $style = array_map(static function ($key, $value) {
            return $key.':'.$value;
        }, array_keys($cssVars), array_values($cssVars));

        return ltrim(implode(';', $style).';', ';');
    }

    protected function buildWallpaperCssVars(array &$cssVars, array $wallpaper)
    {
        $cssVars += [
            'wallpaper-image'  => "url('".$wallpaper['url']."')",
            'wallpaper-filter' => 'none',
            'wallpaper-size'   => 'cover',
            'wallpaper-repeat' => 'no-repeat',
            'wallpaper-color'  => 'transparent',
            'wallpaper-mask'   => 'none',
        ];

        if (! empty($wallpaper['options']['blurred'])) {
            $cssVars['wallpaper-filter'] = 'blur(5px)';
        }

        if (in_array($wallpaper['type'], ['default', 'member_custom_theme'])) {
            $cssVars = [];
        }

        return $cssVars;
    }

    protected function buildColorVars(
        array &$cssVars,
        array $theme,
        \XF\Template\Templater $templater
    ) {
        $cssVars['primary-color'] = $this->parseLessColor(
            $templater,
            $theme['primary_color']
        );
        $cssVars['primary-color-light-filled'] = $this->parseLessColor(
            $templater,
            'fade('.$cssVars['primary-color'].', 10%)'
        );
        $cssVars['primary-color-darken'] = $this->parseLessColor(
            $templater,
            'darken('.$cssVars['primary-color'].', 4%)'
        );
        $cssVars['primary-color-contrast'] = $this->parseLessColor(
            $templater,
            'contrast('.$cssVars['primary-color'].')'
        );

        $cssVars['surface-color'] = $this->parseLessColor(
            $templater,
            $theme['surface_color']
        );
        $cssVars['surface-color-darken'] = $this->parseLessColor(
            $templater,
            'darken('.$cssVars['surface-color'].', 4%)'
        );
        $cssVars['surface-color-contrast'] = $this->parseLessColor(
            $templater,
            'contrast('.$cssVars['surface-color'].')'
        );
        $cssVars['surface-color-muted'] = $this->parseLessColor(
            $templater,
            'fade(contrast('.$cssVars['surface-color'].'), 50%)'
        );
        $cssVars['surface-color-secondary'] = $this->parseLessColor(
            $templater,
            'fade(contrast('.$cssVars['surface-color'].'), 55%)'
        );

        $cssVars['border-color'] = $this->parseLessColor(
            $templater,
            'darken('.$cssVars['surface-color'].', 10%)'
        );
    }


    protected function buildBubbleCssVars(
        array &$cssVars,
        array $theme,
        \XF\Template\Templater $templater
    ) {
        $bubbleBackground = $theme['bubble'];
        $detailsColor = $theme['bubble_details'];

        $cssVars['bubble-background'] = $this->parseLessColor(
            $templater,
            $bubbleBackground
        );
        $cssVars['details-color'] = $this->parseLessColor(
            $templater,
            $detailsColor
        );

        $bubbleBackground = $cssVars['bubble-background'];
        $cssVars['message-text-color'] = $this->parseLessColor(
            $templater,
            'contrast('.$bubbleBackground.')'
        );

        $cssVars['form-input-bg-color'] = 'var(--surface-color)';

        $cssVars['highlighted-message-bg'] = $this->parseLessColor(
            $templater,
            $theme['highlighted_message']
        );
    }

    protected function buildVisitorBubbleCssVars(
        array &$cssVars,
        array $theme,
        \XF\Template\Templater $templater
    ) {
        $bubbleBackground = $theme['visitor_bubble'];
        $detailsColor = $theme['visitor_bubble_details'];

        $cssVars['visitor-bubble-background'] = $this->parseLessColor(
            $templater,
            $bubbleBackground
        );
        $bubbleBackground = $cssVars['visitor-bubble-background'];
        $cssVars['visitor-details-color'] = $this->parseLessColor(
            $templater,
            $detailsColor
        );
        $cssVars['visitor-message-text-color'] = $this->parseLessColor(
            $templater,
            'contrast('.$bubbleBackground.')'
        );

        $cssVars['visitor-form-input-bg-color'] = 'var(--surface-color)';

        $cssVars['visitor-primary-color-light-filled'] = $this->parseLessColor(
            $templater,
            'fade(darken('.$bubbleBackground.', 20%), 25%)'
        );
    }

    public function fnRoomAvatar(
        \XF\Template\Templater $templater,
        &$escape,
        $room,
        $size,
        $attributes = [],
        $classes = ''
    ) {
        $escape = false;

        $style = $this->assertStyle($templater);

        $size = preg_replace('#[^a-zA-Z0-9_-]#', '', $size);
        $fa = $style->getProperty('rtcDefaultRoomIcon', self::DEFAULT_ROOM_ICON);

        if (is_array($room)) {
            $avatarType = $room['avatar_type'];
            $fa = $room['fa'] ?? $fa;
        } else {
            $avatarType = $room->getAvatarType();
        }

        switch ($avatarType) {
            case 'custom':
                $url = $room->getAvatarUrl($size);
                break;

            case 'default':
            default:
                $url = $style->getProperty('rtcDefaultRoomAvatar');
                if (empty($url)) {
                    return $this->fontAwesomeRoomAvatar(
                        $templater,
                        $escape,
                        $room,
                        $size,
                        $fa,
                        $attributes,
                        $classes
                    );
                }
                break;

            case 'fa':
                return $this->fontAwesomeRoomAvatar(
                    $templater,
                    $escape,
                    $room,
                    $size,
                    $fa,
                    $attributes,
                    $classes
                );
        }

        $attributes['src'] = $url;

        $attrsHtml = $templater->getAttributesAsString($attributes);

        $img = "<img $attrsHtml />";

        $styleVars = $this->getAvatarStyleVars($room['tag']);
        $avatarStyle = '';

        foreach ($styleVars as $key => $value) {
            $avatarStyle .= "$key: $value;";
        }

        return "<div class='rtc-room-avatar size--$size $classes' style='$avatarStyle'>$img</div>";
    }

    protected function fontAwesomeRoomAvatar(
        \XF\Template\Templater $templater,
        &$escape,
        $room,
        $size,
        $iconClasses = self::DEFAULT_ROOM_ICON,
        $attributes = [],
        $classes = ''
    ) {
        $icon = $templater->fontAwesome($iconClasses);
        $styleVars = $this->getAvatarStyleVars($room['tag']);
        $style = '';

        foreach ($styleVars as $key => $value) {
            $style .= "$key: $value;";
        }

        return "<div class='rtc-room-avatar size--$size rtc-room-avatar--fontAwesome $classes' style='$style'>$icon</div>";
    }

    protected function getAvatarStyleVars(string $tag)
    {
        $bytes = md5($tag, true);

        $r = dechex(round(5 * ord($bytes[0]) / 255) * 0x33);
        $g = dechex(round(5 * ord($bytes[1]) / 255) * 0x33);
        $b = dechex(round(5 * ord($bytes[2]) / 255) * 0x33);
        $hexBgColor = sprintf('%02s%02s%02s', $r, $g, $b);

        $hslBgColor = \XF\Util\Color::hexToHsl($hexBgColor);

        $bgChanged = false;
        if ($hslBgColor[1] > 60) {
            $hslBgColor[1] = 60;
            $bgChanged = true;
        } elseif ($hslBgColor[1] < 15) {
            $hslBgColor[1] = 15;
            $bgChanged = true;
        }

        if ($hslBgColor[2] > 85) {
            $hslBgColor[2] = 85;
            $bgChanged = true;
        } elseif ($hslBgColor[2] < 15) {
            $hslBgColor[2] = 15;
            $bgChanged = true;
        }

        if ($bgChanged) {
            $hexBgColor = \XF\Util\Color::hslToHex($hslBgColor);
        }

        $hslColor = \XF\Util\Color::darkenOrLightenHsl($hslBgColor, 35);
        $hexColor = \XF\Util\Color::hslToHex($hslColor);

        $bgColor = '#'.$hexBgColor;
        $color = '#'.$hexColor;

        return [
            '--background' => $bgColor,
            '--color'      => $color
        ];
    }

    /**
     * Parse less color with parse_less_color from Templater
     * But skip if color has alpha channel
     * Because parse_less_color doesn't support alpha channel
     *
     * @param  \XF\Template\Templater  $templater
     * @param  string  $value
     * @return void
     */
    protected function parseLessColor(\XF\Template\Templater $templater, string $value)
    {
        if (strpos($value, 'rgba') === 0) {
            return $value;
        }

        return $templater->func('parse_less_color', [$value]);
    }

    public function fnArrayFilter(
        \XF\Template\Templater $templater,
        &$escape,
        $array
    ) {
        $escape = false;

        if (! is_array($array)) {
            return [];
        }

        return array_filter($array);
    }

    public function fnRelativeDate(
        \XF\Template\Templater $templater,
        &$escape,
        $timestamp
    ) {
        $escape = false;

        if (! $timestamp) {
            return \XF::phrase('never');
        }

        $lang = \XF::app()->userLanguage(\XF::visitor());

        $format = 'H:i';

        $day = $lang->date($timestamp, 'day');
        $year = $lang->date($timestamp, 'year');

        $currentDay = $lang->date(\XF::$time, 'day');
        $currentYear = $lang->date(\XF::$time, 'year');

        if ($day !== $currentDay) {
            // if was on this week, show day name
            // else show month and day
            $format = \XF::$time - $timestamp < 86400 * 7 ? 'D' : 'M d';
        }

        if ($year !== $currentYear) {
            $format = 'd/m/Y';
        }

        return $lang->date($timestamp, $format);
    }

    protected function assertStyle(\XF\Template\Templater $templater)
    {
        $style = $templater->getStyle();
        $style ??= \XF::app()->style();

        return $style;
    }

    public function fnDay(
        \XF\Template\Templater $templater,
        &$escape,
        $timestamp
    ) {
        if (! $timestamp) {
            return '';
        }

        $lang = \XF::app()->userLanguage(\XF::visitor());

        $postYear = $lang->date($timestamp, 'year');
        $currentYear = $lang->date(\XF::$time, 'year');
        if ($postYear === $currentYear) {
            return $lang->date($timestamp, 'F d');
        }

        return $lang->date($timestamp, 'F d, Y');
    }

    public function fnDayTs(
        \XF\Template\Templater $templater,
        &$escape,
        $timestamp
    ) {
        if (! $timestamp) {
            return 0;
        }

        $dateTime = new \DateTime('@'.$timestamp);
        $dateTime->setTimezone(new \DateTimeZone(\XF::visitor()->timezone));
        $dateTime->setTime(0, 0, 0);
        return $dateTime->getTimestamp();
    }

    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater $templater)
    {
        $templaterClass = \XF::extendClass(self::class);
        $ownTemplater = new $templaterClass();

        $templater->addFunction('rtc_room_avatar', [$ownTemplater, 'fnRoomAvatar']);
        $templater->addFunction('rtc_theme', [$ownTemplater, 'fnTheme']);
        $templater->addFunction('rtc_room_theme', [$ownTemplater, 'fnRoomTheme']);
        $templater->addFunction('rtc_array_filter', [$ownTemplater, 'fnArrayFilter']);
        $templater->addFunction('rtc_relative_date', [$ownTemplater, 'fnRelativeDate']);
        $templater->addFunction('rtc_day', [$ownTemplater, 'fnDay']);
        $templater->addFunction('rtc_day_ts', [$ownTemplater, 'fnDayTs']);
    }

    public static function helperJsGlobalPreRender(
        \XF\Template\Templater $templater,
        &$type,
        &$template,
        &$name,
        array &$arguments,
        array &$globalVars
    ) {
        $config = \XF::app()->config('rtc') ?? [];

        $cdnConfig = $config['devsell']['cdn'] ?? [];

        $app = \XF::app();
        $options = $app->options();

        $needCdnToLoad = $options->rtcPopup
            || $app->offsetExists('hasRtcWidget')
            || preg_match('/^\/chat\/?/', $app->request()->getRequestUri());

        $cdnConfig['shouldLoad'] = \XF::visitor()->canViewChat() && $needCdnToLoad;
        $cdnConfig['popupModule'] = \XF::options()->rtcPopup;

        $globalVars['rtcDevsellCdn'] = $cdnConfig;
    }
}