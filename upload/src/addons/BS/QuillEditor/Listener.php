<?php

namespace BS\QuillEditor;

class Listener
{
    public const DEFAULT_TOOLBAR = [
        [
            'bold',
            'italic',
            'underline',
            'strike',
            [
                'color' => [
                    'rgb(244, 67, 54)',
                    'rgb(233, 30, 99)',
                    'rgb(156, 39, 176)',
                    'rgb(103, 58, 183)',
                    'rgb(63, 81, 181)',
                    'rgb(33, 150, 243)',
                    'rgb(3, 169, 244)',
                    'rgb(0, 188, 212)',
                    'rgb(0, 150, 136)',
                    'rgb(76, 175, 80)',
                    '#33cc33',
                    'rgb(139, 195, 74)',
                    'rgb(205, 220, 57)',
                    'rgb(255, 235, 59)',
                    'rgb(255, 193, 7)',
                    'rgb(255, 152, 0)',
                    'rgb(255, 87, 34)',
                    'rgb(121, 85, 72)',
                    'rgb(158, 158, 158)',
                    'rgb(96, 125, 139)',
                ]
            ]
        ],

        ['link'],

        ['clean']
    ];

    public const KNOWN_FORMATS = [
        'bold',
        'italic',
        'underline',
        'strike',
        'color',
        'link',
        'blockquote',
        //'list',
        'header',
        'code',
        'code-block',
        'smilie',
        'mention',
        'align'
    ];

    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunction(
            'quill_editor',
            function ($templater, &$escape, array $options = [], string $context = 'default') {
                $escape = false;

                $options['name'] ??= 'message';

                $options['toolbar'] ??= [];
                $options['toolbar'] = array_merge(self::DEFAULT_TOOLBAR, $options['toolbar']);

                $options['formats'] ??= [];

                if (! empty($options['allowedBbCodes'])) {
                    $options['toolbar'] = self::getToolbarFromBbCodes($options['allowedBbCodes']);
                    $options['formats'] = self::getFormatsFromBbCodes($options['allowedBbCodes']);
                } else if (empty($options['formats'])) {
                    $options['formats'] = self::KNOWN_FORMATS;
                }

                if (! in_array('smilie', $options['formats'], true)) {
                    $options['formats'][] = 'smilie';
                }

                \XF::fire('quill_options', [&$options, $context]);

                return $templater->renderTemplate('public:quill_editor', $options);
            }
        );
    }

    protected static function getToolbarFromBbCodes(array $bbCodes): array
    {
        $toolbar = self::DEFAULT_TOOLBAR;

        if (empty($bbCodes)) {
            return $toolbar;
        }

        [$firstSection, $secondSection] = $toolbar;

        if (empty($bbCodes['b'])) {
            $firstSection = array_diff($firstSection, ['bold']);
        }

        if (empty($bbCodes['i'])) {
            $firstSection = array_diff($firstSection, ['italic']);
        }

        if (empty($bbCodes['u'])) {
            $firstSection = array_diff($firstSection, ['underline']);
        }

        if (empty($bbCodes['s'])) {
            $firstSection = array_diff($firstSection, ['strike']);
        }

        if (empty($bbCodes['color'])) {
            $firstSection = array_filter($firstSection, static function ($item) {
                if (is_array($item) && array_key_exists('color', $item)) {
                    return false;
                }

                return $item !== 'color';
            });
        }

        if (empty($bbCodes['url'])) {
            $secondSection = array_diff($secondSection, ['link']);
        }

        $toolbar[0] = $firstSection;
        $toolbar[1] = $secondSection;

        return $toolbar;
    }

    protected static function getFormatsFromBbCodes(array $bbCodes)
    {
        $formats = [];

        if (empty($bbCodes)) {
            return $formats;
        }

        if (! empty($bbCodes['b'])) {
            $formats[] = 'bold';
        }

        if (! empty($bbCodes['i'])) {
            $formats[] = 'italic';
        }

        if (! empty($bbCodes['u'])) {
            $formats[] = 'underline';
        }

        if (! empty($bbCodes['s'])) {
            $formats[] = 'strike';
        }

        if (! empty($bbCodes['color'])) {
            $formats[] = 'color';
        }

        if (! empty($bbCodes['url'])) {
            $formats[] = 'link';
        }

        if (! empty($bbCodes['quote'])) {
            $formats[] = 'blockquote';
        }

        if (! empty($bbCodes['icode'])) {
            $formats[] = 'code';
        }

        if (! empty($bbCodes['code'])) {
            $formats[] = 'code-block';
        }

        if (! empty($bbCodes['list'])) {
            $formats[] = 'list';
        }

        if (! empty($bbCodes['heading'])) {
            $formats[] = 'header';
        }

        if (! empty($bbCodes['user'])) {
            $formats[] = 'mention';
        }

        if (! empty($bbCodes['right']) || ! empty($bbCodes['center']) || ! empty($bbCodes['justify'])) {
            $formats[] = 'align';
        }

        return $formats;
    }
}
