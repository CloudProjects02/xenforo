<?php

namespace BS\RealTimeChat\Service;

use BS\ChatGPTBots\Exception\FunctionCallException;
use BS\ChatGPTBots\Response;
use Orhanerday\OpenAi\OpenAi;

class Translator extends \XF\Service\AbstractService
{
    public const LANGUAGES = [
        'af' => [
            'name' => 'Afrikaans',
            'title' => 'Afrikaans'
        ],
        'am' => [
            'name' => 'Amharic',
            'title' => 'አማርኛ'
        ],
        'ar' => [
            'name' => 'Arabic',
            'title' => 'العربية'
        ],
        'az' => [
            'name' => 'Azerbaijani',
            'title' => 'Azərbaycanca'
        ],
        'bg' => [
            'name' => 'Bulgarian',
            'title' => 'Български'
        ],
        'bm' => [
            'name' => 'Bambara',
            'title' => 'Bamanankan'
        ],
        'bn' => [
            'name' => 'Bengali',
            'title' => 'বাংলা'
        ],
        'cs' => [
            'name' => 'Czech',
            'title' => 'Čeština'
        ],
        'cy' => [
            'name' => 'Welsh',
            'title' => 'Cymraeg'
        ],
        'da' => [
            'name' => 'Danish',
            'title' => 'Dansk'
        ],
        'de' => [
            'name' => 'German',
            'title' => 'Deutsch'
        ],
        'el' => [
            'name' => 'Greek',
            'title' => 'Ελληνικά'
        ],
        'en' => [
            'name' => 'English',
            'title' => 'English'
        ],
        'es' => [
            'name' => 'Spanish',
            'title' => 'Español'
        ],
        'et' => [
            'name' => 'Estonian',
            'title' => 'Eesti'
        ],
        'eu' => [
            'name' => 'Basque',
            'title' => 'Euskara'
        ],
        'fa' => [
            'name' => 'Persian',
            'title' => 'فارسی'
        ],
        'fi' => [
            'name' => 'Finnish',
            'title' => 'Suomi'
        ],
        'fj' => [
            'name' => 'Fijian',
            'title' => 'vosa Vakaviti'
        ],
        'fr' => [
            'name' => 'French',
            'title' => 'Français'
        ],
        'ga' => [
            'name' => 'Irish',
            'title' => 'Gaeilge'
        ],
        'gl' => [
            'name' => 'Galician',
            'title' => 'Galego'
        ],
        'gu' => [
            'name' => 'Gujarati',
            'title' => 'ગુજરાતી'
        ],
        'ha' => [
            'name' => 'Hausa',
            'title' => 'Hausa'
        ],
        'haw' => [
            'name' => 'Hawaiian',
            'title' => 'ʻŌlelo Hawaiʻi'
        ],
        'he' => [
            'name' => 'Hebrew',
            'title' => 'עברית'
        ],
        'hi' => [
            'name' => 'Hindi',
            'title' => 'हिन्दी'
        ],
        'hr' => [
            'name' => 'Croatian',
            'title' => 'Hrvatski'
        ],
        'hu' => [
            'name' => 'Hungarian',
            'title' => 'Magyar'
        ],
        'id' => [
            'name' => 'Indonesian',
            'title' => 'Bahasa Indonesia'
        ],
        'ig' => [
            'name' => 'Igbo',
            'title' => 'Asụsụ Igbo'
        ],
        'is' => [
            'name' => 'Icelandic',
            'title' => 'Íslenska'
        ],
        'it' => [
            'name' => 'Italian',
            'title' => 'Italiano'
        ],
        'iw' => [
            'name' => 'Hebrew',
            'title' => 'עברית'
        ],
        'ja' => [
            'name' => 'Japanese',
            'title' => '日本語'
        ],
        'ka' => [
            'name' => 'Georgian',
            'title' => 'ქართული'
        ],
        'kg' => [
            'name' => 'Kongo',
            'title' => 'Kikongo'
        ],
        'kk' => [
            'name' => 'Kazakh',
            'title' => 'Қазақша'
        ],
        'kl' => [
            'name' => 'Greenlandic',
            'title' => 'Kalaallisut'
        ],
        'ko' => [
            'name' => 'Korean',
            'title' => '한국어'
        ],
        'ky' => [
            'name' => 'Kyrgyz',
            'title' => 'Кыргызча'
        ],
        'ln' => [
            'name' => 'Lingala',
            'title' => 'Lingála'
        ],
        'lt' => [
            'name' => 'Lithuanian',
            'title' => 'Lietuvių'
        ],
        'lv' => [
            'name' => 'Latvian',
            'title' => 'Latviešu'
        ],
        'mi' => [
            'name' => 'Māori',
            'title' => 'te reo Māori'
        ],
        'mk' => [
            'name' => 'Macedonian',
            'title' => 'Македонски'
        ],
        'ml' => [
            'name' => 'Malayalam',
            'title' => 'മലയാളം'
        ],
        'mr' => [
            'name' => 'Marathi',
            'title' => 'मराठी'
        ],
        'ms' => [
            'name' => 'Malay',
            'title' => 'Bahasa Melayu'
        ],
        'ne' => [
            'name' => 'Nepali',
            'title' => 'नेपाली'
        ],
        'nl' => [
            'name' => 'Dutch',
            'title' => 'Nederlands'
        ],
        'no' => [
            'name' => 'Norwegian',
            'title' => 'Norsk'
        ],
        'nr' => [
            'name' => 'South Ndebele',
            'title' => 'isiNdebele'
        ],
        'nso' => [
            'name' => 'Northern Sotho',
            'title' => 'Sesotho sa Leboa'
        ],
        'ny' => [
            'name' => 'Chichewa',
            'title' => 'chiChewa'
        ],
        'pa' => [
            'name' => 'Punjabi',
            'title' => 'ਪੰਜਾਬੀ'
        ],
        'pl' => [
            'name' => 'Polish',
            'title' => 'Polski'
        ],
        'pt' => [
            'name' => 'Portuguese',
            'title' => 'Português'
        ],
        'ro' => [
            'name' => 'Romanian',
            'title' => 'Română'
        ],
        'ru' => [
            'name' => 'Russian',
            'title' => 'Русский'
        ],
        'rw' => [
            'name' => 'Kinyarwanda',
            'title' => 'Ikinyarwanda'
        ],
        'si' => [
            'name' => 'Sinhala',
            'title' => 'සිංහල'
        ],
        'sk' => [
            'name' => 'Slovak',
            'title' => 'Slovenčina'
        ],
        'sl' => [
            'name' => 'Slovenian',
            'title' => 'Slovenščina'
        ],
        'sm' => [
            'name' => 'Samoan',
            'title' => 'Gagana Samoa'
        ],
        'sn' => [
            'name' => 'Shona',
            'title' => 'chiShona'
        ],
        'so' => [
            'name' => 'Somali',
            'title' => 'Soomaali'
        ],
        'sq' => [
            'name' => 'Albanian',
            'title' => 'Shqip'
        ],
        'sr' => [
            'name' => 'Serbian',
            'title' => 'Српски'
        ],
        'ss' => [
            'name' => 'Swati',
            'title' => 'siSwati'
        ],
        'st' => [
            'name' => 'Southern Sotho',
            'title' => 'Sesotho'
        ],
        'sv' => [
            'name' => 'Swedish',
            'title' => 'Svenska'
        ],
        'sw' => [
            'name' => 'Swahili',
            'title' => 'Kiswahili'
        ],
        'ta' => [
            'name' => 'Tamil',
            'title' => 'தமிழ்'
        ],
        'te' => [
            'name' => 'Telugu',
            'title' => 'తెలుగు'
        ],
        'tg' => [
            'name' => 'Tajik',
            'title' => 'Тоҷикӣ'
        ],
        'th' => [
            'name' => 'Thai',
            'title' => 'ไทย'
        ],
        'tk' => [
            'name' => 'Turkmen',
            'title' => 'Türkmençe'
        ],
        'tn' => [
            'name' => 'Tswana',
            'title' => 'Setswana'
        ],
        'to' => [
            'name' => 'Tongan',
            'title' => 'lea fakatonga'
        ],
        'tr' => [
            'name' => 'Turkish',
            'title' => 'Türkçe'
        ],
        'ts' => [
            'name' => 'Tsonga',
            'title' => 'Xitsonga'
        ],
        'uk' => [
            'name' => 'Ukrainian',
            'title' => 'Українська'
        ],
        'ur' => [
            'name' => 'Urdu',
            'title' => 'اردو'
        ],
        'uz' => [
            'name' => 'Uzbek',
            'title' => 'Oʻzbekcha'
        ],
        've' => [
            'name' => 'Venda',
            'title' => 'Tshivenḓa'
        ],
        'vi' => [
            'name' => 'Vietnamese',
            'title' => 'Tiếng Việt'
        ],
        'xh' => [
            'name' => 'Xhosa',
            'title' => 'isiXhosa'
        ],
        'yo' => [
            'name' => 'Yoruba',
            'title' => 'Yorùbá'
        ],
        'zh' => [
            'name' => 'Chinese',
            'title' => '中文'
        ],
        'zu' => [
            'name' => 'Zulu',
            'title' => 'isiZulu'
        ]
    ];

    protected OpenAi $openAi;

    protected function setup()
    {
        $this->openAi = $this->app->container('chatGPT');
    }

    public function translateWithOpenAI(string $text, string $toLanguageCode): ?array
    {
        $messageRepo = $this->getMessageRepo();

        try {
            $response = Response::getFunctionCallWithLogErrors($this->openAi, [
                'model'             => \XF::options()->rtcTranslationGptModel,
                'messages'          => [
                    $messageRepo->wrapMessage('You are translator API with BB-codes support.', 'system'),
                    $messageRepo->wrapMessage($text)
                ],
                'functions'         => [
                    $this->getTranslateFunctionCall($toLanguageCode)
                ],
                'function_call'     => [
                    'name' => 'translate_text',
                ],
                'temperature'       => 0.1,
                'frequency_penalty' => 0,
                'presence_penalty'  => 0,
            ], true);
        } catch (FunctionCallException $e) {
            return null;
        }

        if (isset($response['arguments']) && is_string($response['arguments'])) {
            $response['arguments'] = @json_decode($response['arguments'], true);

            return is_array($response['arguments']) ? $response['arguments'] : null;
        }

        return null;
    }

    protected function getTranslateFunctionCall(string $toLanguageCode): array
    {
        $toLanguage = self::LANGUAGES[$toLanguageCode]['name'] ?? $toLanguageCode;
        return Response::buildFunctionForCall(
            'translate_text',
            [
                'translated_text' => [
                    'type' => 'string',
                    'description' => 'Translated text'
                ],
                'detected_language_code' => [
                    'type' => 'string',
                    'description' => 'The language code of the text'
                ]
            ],
            ['translated_text', 'detected_language_code'],
            "Translate the text to $toLanguage"
        );
    }

    public static function isLanguageSupported(string $languageCode): bool
    {
        return isset(self::LANGUAGES[$languageCode]);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\ChatGPTBots\Repository\Message
     */
    protected function getMessageRepo()
    {
        return $this->repository('BS\ChatGPTBots:Message');
    }
}
