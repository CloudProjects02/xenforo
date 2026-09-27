<?php

namespace BS\RealTimeChat\Pub\Controller\Concerns;

use BS\RealTimeChat\Service\Translator;

trait Language
{
    protected function getChatLanguageCode()
    {
        $visitor = \XF::visitor();

        if ($visitor->rtc_language_code) {
            return $visitor->rtc_language_code;
        }

        $browserLanguageCode = $this->getUserBrowserLanguageCode();
        if (Translator::isLanguageSupported($browserLanguageCode)) {
            return $browserLanguageCode;
        }

        $languageCode = $this->app()->userLanguage($visitor)->getLanguageCode();
        return explode('-', $languageCode)[0] ?? 'en';
    }

    protected function getUserBrowserLanguageCode(): string
    {
        preg_match_all(
            '/(\W|^)([a-z]{2})([^a-z]|$)/six',
            $this->request->getServer('HTTP_ACCEPT_LANGUAGE'),
            $matches,
            PREG_PATTERN_ORDER
        );

        $languages = $matches[2] ?? [];

        $firstLanguage = reset($languages);
        return explode('-', $firstLanguage)[0] ?? '';
    }
}
