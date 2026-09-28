<?php

namespace BZ\MenuFlex;

use XF\App as XFApp;
use XF\Entity\Template;
use XF\Entity\Option;

class Options
{
    private static $overrideCounter = [];

    private static function isValidColor(string $color): array
    {
        $color = trim($color);
        if ($color === '' || strpos($color, '@') === 0) {
            return [true, $color];
        }

        $namedColors = ['aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen'];

        if (in_array(strtolower($color), $namedColors)) {
            return [true, $color];
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            return [true, $color];
        }

        if (preg_match('/^(rgb|rgba|hsl|hsla)\((.*?)\)$/i', $color, $matches)) {
            $prefix = strtolower($matches[1]);
            $values = trim($matches[2]);
            $values = preg_replace('/\s*\/\s*/', ' ', $values);
            $parts = array_filter(preg_split('/[\s,]+/', $values), fn($part) => $part !== '');
            $hasAlpha = in_array($prefix, ['rgba', 'hsla']) || count($parts) === 4;

            if (in_array($prefix, ['rgb', 'rgba'])) {
                if (count($parts) < 3 || count($parts) > 4) return [false, $color];
                $r = trim($parts[0]);
                $g = trim($parts[1]);
                $b = trim($parts[2]);
                $a = $hasAlpha ? trim($parts[3] ?? '1') : null;
                if (!is_numeric($r) || $r < 0 || $r > 255 || !is_numeric($g) || $g < 0 || $g > 255 || !is_numeric($b) || $b < 0 || $b > 255) return [false, $color];
                if ($hasAlpha && $a !== null) {
                    if (preg_match('/^\d+%$/', $a)) {
                        $aValue = rtrim($a, '%');
                        if (!is_numeric($aValue) || $aValue < 0 || $aValue > 100) return [false, $color];
                        $a = $aValue / 100;
                    } elseif (!is_numeric($a) || $a < 0 || $a > 1) return [false, $color];
                }
                $normalized = $hasAlpha ? "rgba($r, $g, $b, " . ($a ?? '1') . ")" : "rgb($r, $g, $b)";
            } elseif (in_array($prefix, ['hsl', 'hsla'])) {
                if (count($parts) < 3 || count($parts) > 4) return [false, $color];
                $h = trim($parts[0]);
                $s = trim($parts[1]);
                $l = trim($parts[2]);
                $a = $hasAlpha ? trim($parts[3] ?? '1') : null;
                if (!is_numeric($h) || $h < 0 || $h > 360 || !preg_match('/^\d{1,3}%$/', $s) || rtrim($s, '%') > 100 || !preg_match('/^\d{1,3}%$/', $l) || rtrim($l, '%') > 100) return [false, $color];
                if ($hasAlpha && $a !== null) {
                    if (preg_match('/^\d+%$/', $a)) {
                        $aValue = rtrim($a, '%');
                        if (!is_numeric($aValue) || $aValue < 0 || $aValue > 100) return [false, $color];
                        $a = $aValue / 100;
                    } elseif (!is_numeric($a) || $a < 0 || $a > 1) return [false, $color];
                }
                $normalized = $hasAlpha ? "hsla($h, $s, $l, " . ($a ?? '1') . ")" : "hsl($h, $s, $l)";
            } else {
                return [false, $color];
            }
            return [true, $normalized];
        }
        return [false, $color];
    }

    private static function buildIconRule(string $key, string $iconName, string $style, string $globalStyle, string $type = 'navid', bool $commented = false, ?string $color = null, array $standardAssignments = [], ?int $overrideNumber = null, ?string $overrideSuffix = null, bool $noComments = false): string
    {
        $defaultIcon = 'question-circle';
        $validStyles = ['light', 'regular', 'solid', 'duotone', 'brands'];
        $iconOmitted = $iconName === '';

        $baseRule = $type === 'navid' ? "&[data-nav-id='{$key}']" : "[href*=\"{$key}\"]";
        if ($iconName === '' && count(explode(':', $key . ':' . $iconName . ':' . $style . ':' . ($color ?? ''))) === 2) {
            $rule = "    {$baseRule}:before { content: ''; }";
            return $noComments || !$commented ? "{$rule}\n" : "    // " . \XF::phrase('option.bzmf_overridden') . " #{$overrideNumber}a {$rule}\n";
        } elseif ($iconName === '' && isset($standardAssignments[$key])) {
            $existingParts = explode(':', $standardAssignments[$key], 3);
            $iconName = trim($existingParts[1] ?? $defaultIcon);
        } elseif ($iconName === '' || in_array(strtolower($iconName), ['blank', 'empty'])) {
            $iconName = $defaultIcon;
        } elseif (strtolower($iconName) === 'none') {
            $rule = $type === 'navid' ? "    {$baseRule} { display: none; }" : "    {$baseRule} { display: none !important; }";
            return $noComments || !$commented ? "{$rule}\n" : "    // " . \XF::phrase('option.bzmf_overridden') . " #{$overrideNumber}a {$rule}\n";
        }

        $isValidIcon = preg_match('/^[a-z-]+$/i', $iconName);
        $usedIconName = $isValidIcon ? $iconName : $defaultIcon;

        $styleProvided = $style !== $globalStyle;
        $isValidStyle = in_array($style, $validStyles);
        $usedStyle = $isValidStyle ? $style : $globalStyle;

        $rule = "    {$baseRule}:before { .m-faContent(@fa-var-{$usedStyle}-{$usedIconName}) !important;";
        if ($commented && !$noComments) {
            $rule = "    // " . \XF::phrase('option.bzmf_overridden') . " #{$overrideNumber}a {$rule}";
        }

        if ($color !== null) {
            if ($color === '') {
                $rule .= " }\n";
                if (!$noComments) $rule .= "    // " . \XF::phrase('option.bzmf_omitted_color') . "\n";
            } else {
                [$isValid, $normalizedColor] = self::isValidColor($color);
                if ($isValid) {
                    $rule .= " background-color: {$normalizedColor} !important;";
                }
                $rule .= " }\n";
                if (!$isValid && !$noComments) {
                    $rule .= "    // " . \XF::phrase('option.bzmf_above_color_assignment_removed') . " {$color} " . \XF::phrase('option.bzmf_defined_invalid') . "\n";
                }
            }
        } else {
            $rule .= " }\n";
        }

        if (!$noComments) {
            if (!$isValidIcon) {
                $rule .= "    // " . \XF::phrase('option.bzmf_above_icon_assignment_removed') . " {$iconName} " . \XF::phrase('option.bzmf_defined_invalid') . "\n";
            }
            if ($styleProvided && !$isValidStyle) {
                if ($style === '') {
                    $rule .= "    // " . \XF::phrase('option.bzmf_using_global_style', ['style' => $globalStyle]) . "\n";
                } else {
                    $rule .= "    // " . \XF::phrase('option.bzmf_above_style_assignment_removed') . " {$style} " . \XF::phrase('option.bzmf_defined_invalid') . "\n";
                }
            }
            if ($iconOmitted && isset($standardAssignments[$key])) {
                $rule .= "    // " . \XF::phrase('option.bzmf_omitted_icon_using_existing') . "\n";
            } elseif ($iconOmitted && !isset($standardAssignments[$key])) {
                $rule .= "    // " . \XF::phrase('option.bzmf_omitted_icon_no_assignment') . "\n";
            }
            if ($overrideNumber !== null && $overrideSuffix !== 'a') {
                $rule .= "    // " . \XF::phrase('option.bzmf_override') . " #{$overrideNumber}{$overrideSuffix}\n";
            }
        }

        return $rule;
    }

    private static function processIconAssignments(array $assignments, string $globalStyle, array $validStyles, string $type = 'navid', array $customNavIdKeys = [], array $customURLStringKeys = [], bool $isOverride = false, bool $onlyQuads = false, ?string $selector = null, ?string $comment = null, array $standardAssignments = [], bool $noComments = false): string
    {
        $less = '';
        if ($selector) {
            $less .= "\n";
            if ($comment && !$noComments) {
                $less .= "// {$comment}\n";
            }
            $less .= "{$selector} {\n";
        }

        $overrideCounts = [];
        foreach ($assignments as $assignment) {
            $parts = explode(':', $assignment, 4);
            if (count($parts) < 2 || !$parts[0]) continue;
            $key = trim($parts[0]);
            if ($type === 'navid' && strpos($key, '/') === 0) continue;
            $isQuad = count($parts) === 4;
            if ($onlyQuads && !$isQuad) continue;
            if (!$onlyQuads && $isQuad && $type === 'urlstring') continue;
            $iconName = trim($parts[1] ?? '');
            $style = trim($parts[2] ?? $globalStyle);
            $color = isset($parts[3]) ? trim($parts[3]) : null;
            $commented = $isOverride && (($type === 'navid' && in_array($key, $customNavIdKeys)) || ($type === 'urlstring' && in_array($key, $customURLStringKeys)));

            $overrideNumber = null;
            $suffix = null;
            if ($commented) {
                if (!isset(self::$overrideCounter[$key])) {
                    self::$overrideCounter[$key] = count(self::$overrideCounter) + 1;
                }
                $overrideNumber = self::$overrideCounter[$key];
                $suffix = 'a';
            } elseif (!$isOverride && isset($standardAssignments[$key])) {
                if (!isset(self::$overrideCounter[$key])) {
                    self::$overrideCounter[$key] = count(self::$overrideCounter) + 1;
                }
                $overrideNumber = self::$overrideCounter[$key];
                $overrideCounts[$key] = ($overrideCounts[$key] ?? 0) + 1;
                $suffix = chr(ord('b') + $overrideCounts[$key] - 1);
            }

            $less .= self::buildIconRule($key, $iconName, $style, $globalStyle, $type, $commented, $color, $standardAssignments, $overrideNumber, $suffix, $noComments);
        }

        if ($selector) {
            $less .= "}\n";
        }
        return $less;
    }

    public static function recompileFontAwesome($value, XFApp $app)
    {
        self::$overrideCounter = [];
        $customLess = '';
        $request = $app->request();
        $options = $request->get('options') ?? [];
        $noComments = !empty($options['bzmf_compilewithoutcomments']);
        $phrases = [
            'final_less_opening' => \XF::phrase('option.bzmf_final_less_opening'),
            'final_less_warning' => \XF::phrase('option.bzmf_final_less_warning'),
            'final_less_closing' => \XF::phrase('option.bzmf_final_less_closing'),
            'enabled' => \XF::phrase('enabled'),
            'disabled' => \XF::phrase('disabled'),
            'custom_less_js' => \XF::phrase('option.bzmf_custom_less_js'),
            'custom_less' => \XF::phrase('option.bzmf_custom_less'),
            'openmenusonhover' => \XF::phrase('option.bzmf_openmenusonhover'),
            'tieredmenus' => \XF::phrase('option.bzmf_tieredmenus'),
            'fontawesome' => \XF::phrase('option.bzmf_fontawesome'),
            'icon_color_overrides_global' => \XF::phrase('option.bzmf_icon_color_overrides_global'),
            'icon_color_overrides_primary_main' => \XF::phrase('option.bzmf_icon_color_overrides_primary_main'),
            'icon_color_overrides_primary_whatsnewtabs' => \XF::phrase('option.bzmf_icon_color_overrides_primary_whatsnewtabs'),
            'icon_color_overrides_primary_searchtabs' => \XF::phrase('option.bzmf_icon_color_overrides_primary_searchtabs'),
            'icon_color_overrides_secondary_submenus' => \XF::phrase('option.bzmf_icon_color_overrides_secondary_submenus'),
            'icon_color_overrides_secondary_othermenus' => \XF::phrase('option.bzmf_icon_color_overrides_secondary_othermenus'),
            'facolor_primarycolor_input' => \XF::phrase('option.bzmf_facolor_primarycolor_input'),
            'facolor_secondarycolor_input' => \XF::phrase('option.bzmf_facolor_secondarycolor_input'),
            'defined_invalid' => \XF::phrase('option.bzmf_defined_invalid'),
            'undefined' => \XF::phrase('option.bzmf_undefined'),
            'no' => \XF::phrase('no'),
            'standardiconassignments' => \XF::phrase('option.bzmf_icon_assignments_standard'),
            'customiconassignments' => \XF::phrase('option.bzmf_icon_assignments_custom'),
            'singlespairstrios' => \XF::phrase('option.bzmf_singles_pairs_trios'),
            'quads' => \XF::phrase('option.bzmf_quads'),
            'standardicons' => \XF::phrase('option.bzmf_fa_assignments_standardicons'),
            'mainmenu' => \XF::phrase('option.bzmf_fa_assignments_mainmenu'),
            'submenus' => \XF::phrase('option.bzmf_fa_assignments_submenus'),
            'accountvisitorsmenu' => \XF::phrase('option.bzmf_fa_assignments_accountvisitorsmenu'),
            'moreoptionsmenu' => \XF::phrase('option.bzmf_fa_assignments_moreoptionsmenu'),
            'searchtabs' => \XF::phrase('option.bzmf_fa_assignments_searchtabs'),
            'whatsnewtabs' => \XF::phrase('option.bzmf_fa_assignments_whatsnewtabs'),
            'staffbar' => \XF::phrase('option.bzmf_fa_assignments_staffbar'),
            'assignments' => \XF::phrase('option.bzmf_fa_assignments'),
            'navids' => \XF::phrase('option.bzmf_fa_assignments_navids'),
            'othermenus' => \XF::phrase('option.bzmf_fa_assignments_othermenus')
        ];

        $facolorSetting = $options['bzmf_fontawesomecolor']['faColorMode'] ?? 'default';
        $faPrimaryColor = trim($options['bzmf_fontawesomecolor']['faPrimaryColor'] ?? '');
        $faSecondaryColor = trim($options['bzmf_fontawesomecolor']['faSecondaryColor'] ?? '');
        [$isValid1, $normalizedPrimaryColor] = self::isValidColor($faPrimaryColor);
        [$isValid2, $normalizedSecondaryColor] = self::isValidColor($faSecondaryColor);
        $faPrimaryColor = $isValid1 ? $normalizedPrimaryColor : $faPrimaryColor;
        $faSecondaryColor = $isValid2 ? $normalizedSecondaryColor : $faSecondaryColor;

        $dateTime = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
        $customLess .= "// {$phrases['final_less_opening']} " . $dateTime->format('F d, Y H:i:s T O') . "\n";
        $customLess .= "// {$phrases['final_less_warning']}\n\n";

        if (!empty($options['bzmf_openmenusonhover'])) {
            if (!$noComments) $customLess .= "// {$phrases['openmenusonhover']}: {$phrases['enabled']} - {$phrases['custom_less_js']}\n";
            $customLess .= "@import \"bzmf_openmenusonhover.less\";\n";
        } elseif (!$noComments) {
            $customLess .= "// {$phrases['openmenusonhover']}: {$phrases['disabled']}\n";
        }
        if (!empty($options['bzmf_tieredmenus'])) {
            if (!$noComments) $customLess .= "// {$phrases['tieredmenus']}: {$phrases['enabled']} - {$phrases['custom_less_js']}\n";
            $customLess .= "@import \"bzmf_tieredmenus.less\";\n";
        } elseif (!$noComments) {
            $customLess .= "// {$phrases['tieredmenus']}: {$phrases['disabled']}\n";
        }
        if (!empty($options['bzmf_fontawesome']['fontawesomeicons'])) {
            if (!$noComments) $customLess .= "// {$phrases['fontawesome']}: {$phrases['enabled']} - {$phrases['custom_less']}\n";
            $customLess .= "@import \"bzmf_fa_base.less\";\n";
        } elseif (!$noComments) {
            $customLess .= "// {$phrases['fontawesome']}: {$phrases['disabled']}\n";
        }

        if (!empty($options['bzmf_fontawesome']['fontawesomeicons'])) {
            $em = \XF::em();
            $globalStyle = $options['bzmf_fontawesomestyle'] ?? 'duotone';
            $includeStandard = !empty($options['bzmf_fontawesome']['standardiconassignments']);
            $validStyles = ['light', 'regular', 'solid', 'duotone', 'brands'];

            $subsetNames = [
                'mainmenu' => $phrases['mainmenu'],
                'submenus' => $phrases['submenus'],
                'accountvisitorsmenu' => $phrases['accountvisitorsmenu'],
                'moreoptionsmenu' => $phrases['moreoptionsmenu'],
                'searchtabs' => $phrases['searchtabs'],
                'whatsnewtabs' => $phrases['whatsnewtabs'],
                'staffbar' => $phrases['staffbar']
            ];

            $subsetOptions = [
                'mainmenu' => !empty($options['bzmf_fontawesome']['mainmenu']),
                'submenus' => !empty($options['bzmf_fontawesome']['submenus']),
                'accountvisitorsmenu' => !empty($options['bzmf_fontawesome']['accountvisitorsmenu']),
                'moreoptionsmenu' => !empty($options['bzmf_fontawesome']['moreoptionsmenu']),
                'searchtabs' => !empty($options['bzmf_fontawesome']['searchtabs']),
                'whatsnewtabs' => !empty($options['bzmf_fontawesome']['whatsnewtabs']),
                'staffbar' => !empty($options['bzmf_fontawesome']['staffbar'])
            ];

            $subsetTemplates = [
                'mainmenu' => 'bzmf_fa_assignments_mainmenu',
                'submenus' => 'bzmf_fa_assignments_submenus',
                'accountvisitorsmenu' => 'bzmf_fa_assignments_accountvisitorsmenu',
                'moreoptionsmenu' => 'bzmf_fa_assignments_moreoptionsmenu',
                'searchtabs' => 'bzmf_fa_assignments_searchtabs',
                'whatsnewtabs' => 'bzmf_fa_assignments_whatsnewtabs',
                'staffbar' => 'bzmf_fa_assignments_staffbar'
            ];

            $standardIconAssignments = [];
            foreach ($subsetTemplates as $setKey => $templateName) {
                if ($includeStandard && $subsetOptions[$setKey]) {
                    $template = $em->findOne(\XF\Entity\Template::class, ['style_id' => 0, 'type' => 'public', 'title' => $templateName]);
                    if (!$template) {
                        $standardIconAssignments[$setKey] = ['assignments' => [], 'selector' => null];
                    } else {
                        $rawContent = $template->template;
                        $assignments = array_filter(array_map('trim', explode("\n", $rawContent)), fn($assignment) => $assignment !== '' && strpos($assignment, '#') !== 0);
                        $firstAssignment = reset($assignments);
                        if (!$firstAssignment || strpos($firstAssignment, 'SELECTOR') !== 0) {
                            $standardIconAssignments[$setKey] = ['assignments' => [], 'selector' => null];
                        } else {
                            $selector = trim(substr(array_shift($assignments), 9));
                            $standardIconAssignments[$setKey] = ['assignments' => $assignments, 'selector' => $selector];
                        }
                    }
                } else {
                    $standardIconAssignments[$setKey] = ['assignments' => [], 'selector' => null];
                }
            }

            $customAssignments = array_filter(array_map('trim', explode("\n", $value)), fn($assignment) => $assignment !== '' && strpos($assignment, '#') !== 0);
            $customNavIdKeys = [];
            $customURLStringKeys = [];
            $categories = [
                'navid_nonquad' => [], 'navid_quad' => [],
                'url_search_nonquad' => [], 'url_search_quad' => [],
                'url_whatsnew_nonquad' => [], 'url_whatsnew_quad' => [],
                'url_other_nonquad' => [], 'url_other_quad' => []
            ];
            foreach ($customAssignments as $assignment) {
                $parts = explode(':', $assignment, 4);
                if (count($parts) < 2 || !$parts[0]) continue;
                $key = trim($parts[0]);
                $isQuad = count($parts) === 4;
                if (strpos($key, '/') === 0) {
                    $customURLStringKeys[] = $key;
                    if (str_starts_with($key, '/search/') || str_starts_with($key, '/index.php?search/')) {
                        $cat = $isQuad ? 'url_search_quad' : 'url_search_nonquad';
                    } elseif (str_starts_with($key, '/whats-new/') || str_starts_with($key, '/index.php?whats-new/')) {
                        $cat = $isQuad ? 'url_whatsnew_quad' : 'url_whatsnew_nonquad';
                    } else {
                        $cat = $isQuad ? 'url_other_quad' : 'url_other_nonquad';
                    }
                } else {
                    $customNavIdKeys[] = $key;
                    $cat = $isQuad ? 'navid_quad' : 'navid_nonquad';
                }
                $categories[$cat][] = $assignment;
            }

            if (empty($options['bzmf_fontawesome']['standardiconassignments']) && !$noComments) {
                $customLess .= "\n// {$phrases['standardicons']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['mainmenu']) && !$noComments) {
                $customLess .= "// {$phrases['mainmenu']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['submenus']) && !$noComments) {
                $customLess .= "// {$phrases['submenus']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['accountvisitorsmenu']) && !$noComments) {
                $customLess .= "// {$phrases['accountvisitorsmenu']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['moreoptionsmenu']) && !$noComments) {
                $customLess .= "// {$phrases['moreoptionsmenu']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['searchtabs']) && !$noComments) {
                $customLess .= "// {$phrases['searchtabs']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['whatsnewtabs']) && !$noComments) {
                $customLess .= "// {$phrases['whatsnewtabs']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }
            if (empty($options['bzmf_fontawesome']['staffbar']) && !$noComments) {
                $customLess .= "// {$phrases['staffbar']}: {$phrases['assignments']} {$phrases['disabled']}\n";
            }

            $standardAssignments = [];
            foreach ($standardIconAssignments as $setKey => $setData) {
                if (!empty($setData['assignments'])) {
                    foreach ($setData['assignments'] as $assignment) {
                        $parts = explode(':', $assignment, 3);
                        if (count($parts) >= 2) {
                            $standardAssignments[trim($parts[0])] = $assignment;
                        }
                    }
                }
            }

            foreach ($standardIconAssignments as $setKey => $setData) {
                if (!empty($setData['assignments']) && $setData['selector']) {
                    $type = in_array($setKey, ['mainmenu', 'submenus', 'staffbar']) ? 'navid' : 'urlstring';
                    $nonQuadAssignments = array_filter($setData['assignments'], fn($assignment) => substr_count($assignment, ':') < 3);
                    if (!empty($nonQuadAssignments)) {
                        $customLess .= self::processIconAssignments(
                            $nonQuadAssignments,
                            $globalStyle,
                            $validStyles,
                            $type,
                            $customNavIdKeys,
                            $customURLStringKeys,
                            true,
                            false,
                            $setData['selector'],
                            "{$phrases['standardiconassignments']} ({$phrases['singlespairstrios']}): {$subsetNames[$setKey]}",
                            $standardAssignments,
                            $noComments
                        );
                    }
                    $quadAssignments = array_filter($setData['assignments'], fn($assignment) => substr_count($assignment, ':') === 3);
                    if (!empty($quadAssignments)) {
                        $customLess .= self::processIconAssignments(
                            $quadAssignments,
                            $globalStyle,
                            $validStyles,
                            $type,
                            $customNavIdKeys,
                            $customURLStringKeys,
                            true,
                            true,
                            $setData['selector'],
                            "{$phrases['standardiconassignments']} ({$phrases['quads']}): {$subsetNames[$setKey]}",
                            $standardAssignments,
                            $noComments
                        );
                    }
                }
            }

            if (!empty($categories['navid_nonquad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['navid_nonquad'],
                    $globalStyle,
                    $validStyles,
                    'navid',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    false,
                    ".p-navEl a, .p-nav-opposite a, .p-staffBar a, .menu .menu-content a, .offCanvasMenu-linkHolder a, .offCanvasMenu-subList a",
                    "{$phrases['customiconassignments']} ({$phrases['singlespairstrios']}): {$phrases['navids']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_search_nonquad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_search_nonquad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    false,
                    "[data-template='search_form']",
                    "{$phrases['customiconassignments']} ({$phrases['singlespairstrios']}): {$phrases['searchtabs']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_whatsnew_nonquad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_whatsnew_nonquad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    false,
                    ".tabs.tabs--standalone",
                    "{$phrases['customiconassignments']} ({$phrases['singlespairstrios']}): {$phrases['whatsnewtabs']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_other_nonquad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_other_nonquad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    false,
                    ".menu .menu-content",
                    "{$phrases['customiconassignments']} ({$phrases['singlespairstrios']}): {$phrases['othermenus']}",
                    $standardAssignments,
                    $noComments
                );
            }

            if ($facolorSetting === 'custom') {
                if (!$noComments) $customLess .= "\n// {$phrases['icon_color_overrides_global']}\n";
                if ($faPrimaryColor !== '') {
                    if ($isValid1) {
                        if (!$noComments) $customLess .= "// {$phrases['icon_color_overrides_primary_main']}\n";
                        $customLess .= ".p-navEl a, .p-nav-opposite a, .p-staffBar a, .offCanvasMenu-linkHolder a, .offCanvasMenu-subList a {\n";
                        $customLess .= "    &[data-nav-id]:before { background-color: {$faPrimaryColor} !important; }\n";
                        $customLess .= "}\n";
                        if (!$noComments) $customLess .= "// {$phrases['icon_color_overrides_primary_whatsnewtabs']}\n";
                        $customLess .= ".tabs.tabs--standalone [href]:before {\n";
                        $customLess .= "    background-color: {$faPrimaryColor} !important;\n";
                        $customLess .= "}\n";
                        if (!$noComments) $customLess .= "// {$phrases['icon_color_overrides_primary_searchtabs']}\n";
                        $customLess .= "[data-template='search_form'] [href]:before {\n";
                        $customLess .= "    background-color: {$faPrimaryColor} !important;\n";
                        $customLess .= "}\n";
                    } elseif (!$noComments) {
                        $customLess .= "// {$phrases['facolor_primarycolor_input']} {$faPrimaryColor} {$phrases['defined_invalid']}\n";
                    }
                } elseif (!$noComments) {
                    $customLess .= "// {$phrases['facolor_primarycolor_input']} {$phrases['undefined']}\n";
                }
                if ($faSecondaryColor !== '') {
                    if ($isValid2) {
                        if (!$noComments) $customLess .= "// {$phrases['icon_color_overrides_secondary_submenus']}\n";
                        $customLess .= ".menu .menu-content a {\n";
                        $customLess .= "    &[data-nav-id]:before { background-color: {$faSecondaryColor} !important; }\n";
                        $customLess .= "}\n";
                        if (!$noComments) $customLess .= "// {$phrases['icon_color_overrides_secondary_othermenus']}\n";
                        $customLess .= ".menu .menu-content {\n";
                        $customLess .= "    [href]:not([data-nav-id]):before { background-color: {$faSecondaryColor} !important; }\n";
                        $customLess .= "}\n";
                    } elseif (!$noComments) {
                        $customLess .= "// {$phrases['facolor_secondarycolor_input']} {$faSecondaryColor} {$phrases['defined_invalid']}\n";
                    }
                } elseif (!$noComments) {
                    $customLess .= "// {$phrases['facolor_secondarycolor_input']} {$phrases['undefined']}\n";
                }
            } elseif (!$noComments) {
                $customLess .= "\n// {$phrases['no']} {$phrases['icon_color_overrides_global']}\n";
            }

            if (!empty($categories['navid_quad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['navid_quad'],
                    $globalStyle,
                    $validStyles,
                    'navid',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    true,
                    ".p-navEl a, .p-nav-opposite a, .p-staffBar a, .menu .menu-content a, .offCanvasMenu-linkHolder a, .offCanvasMenu-subList a",
                    "{$phrases['customiconassignments']} ({$phrases['quads']}): {$phrases['navids']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_search_quad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_search_quad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    true,
                    "[data-template='search_form']",
                    "{$phrases['customiconassignments']} ({$phrases['quads']}): {$phrases['searchtabs']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_whatsnew_quad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_whatsnew_quad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    true,
                    ".tabs.tabs--standalone",
                    "{$phrases['customiconassignments']} ({$phrases['quads']}): {$phrases['whatsnewtabs']}",
                    $standardAssignments,
                    $noComments
                );
            }
            if (!empty($categories['url_other_quad'])) {
                $customLess .= self::processIconAssignments(
                    $categories['url_other_quad'],
                    $globalStyle,
                    $validStyles,
                    'urlstring',
                    $customNavIdKeys,
                    $customURLStringKeys,
                    false,
                    true,
                    ".menu .menu-content",
                    "{$phrases['customiconassignments']} ({$phrases['quads']}): {$phrases['othermenus']}",
                    $standardAssignments,
                    $noComments
                );
            } elseif (empty($categories['navid_quad']) && empty($categories['url_search_quad']) && empty($categories['url_whatsnew_quad']) && empty($categories['url_other_quad']) && !$noComments) {
                $customLess .= "\n// {$phrases['no']} {$phrases['customiconassignments']} ({$phrases['quads']})\n";
            }
        }

        $customLess = rtrim($customLess, "\n") . "\n\n// {$phrases['final_less_closing']}";
        $em = \XF::em();
        $template = $em->findOne(\XF\Entity\Template::class, ['style_id' => 0, 'type' => 'public', 'title' => 'bzmf_final.less']);
        if (!$template) {
            $template = $em->create('XF:Template');
            $template->bulkSet([
                'title' => 'bzmf_final.less',
                'type' => 'public',
                'addon_id' => 'BZ/MenuFlex',
                'style_id' => 0,
                'version_id' => 1,
                'version_string' => '1.0.0',
                'template' => $customLess
            ]);
        } else {
            $template->version_id++;
            $template->template = $customLess;
        }
        return $template->save();
    }

    public static function validateFontAwesomeCustom($value, Option $option)
    {
        $assignments = explode("\n", $value);
        $cleanedAssignments = [];
        $baseAllowed = '[a-zA-Z0-9_:\-\s\t#\/]';
        $urlAllowed = '[a-zA-Z0-9_:\-\s\t#\/\?=&]';
        $invalidChars = [];

        foreach ($assignments as $assignment) {
            $trimmedAssignment = trim($assignment);
            if ($trimmedAssignment === '' || strpos($trimmedAssignment, '#') === 0) {
                $cleanedAssignments[] = $trimmedAssignment;
                continue;
            }
            $parts = explode(':', $assignment, 4);
            $key = trim($parts[0]);
            $regex = strpos($key, '/') === 0 ? "/^{$urlAllowed}*$/" : "/^{$baseAllowed}*$/";
            $cleanedAssignment = preg_replace("/[^" . ($regex === "/^{$urlAllowed}*$/" ? $urlAllowed : $baseAllowed) . "]/", '', $assignment);
            if ($cleanedAssignment !== $assignment) {
                foreach (str_split($assignment) as $i => $char) {
                    if (!preg_match($regex, $char)) $invalidChars[] = "Pos $i in '$assignment': " . bin2hex($char);
                }
            }
            if (count($parts) === 4) {
                $color = trim($parts[3]);
                [$isValid, $normalizedColor] = self::isValidColor($color);
                if ($isValid) {
                    $cleanedAssignment = implode(':', array_slice($parts, 0, 3)) . ':' . $normalizedColor;
                }
            }
            $cleanedAssignments[] = $cleanedAssignment;
        }

        $cleanedValue = implode("\n", $cleanedAssignments);
        return self::recompileFontAwesome($cleanedValue, \XF::app());
    }
}