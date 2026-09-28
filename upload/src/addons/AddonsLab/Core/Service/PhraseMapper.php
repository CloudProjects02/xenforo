<?php

namespace AddonsLab\Core\Service;

use AddonsLab\Core\PhraseProviderInterface;

class PhraseMapper
{
    protected $phrase_provider;

    public function __construct(PhraseProviderInterface $phraseProvider)
    {
        $this->phrase_provider = $phraseProvider;
    }

    /**
     * @param array $phraseKeyArray
     * @return array
     * Maps array of phrase keys to phrases
     */
    public function mapPhrases(array $phraseKeyArray)
    {
        return array_map(array($this, 'renderPhrase'), $phraseKeyArray);
    }

    /**
     * @param $phraseKey
     * @return mixed
     * Renders a phrase turning it into ab object if phrase ID is provided. Supports params in grouped array and in the same array as phraseKey
     * Expect argument formats:
     * phraseId: string
     * [0=>phraseId: string, 1=>params: array]
     * [0=>phraseId: string, [...param: mixed]]
     */
    public function renderPhrase($phraseKey)
    {
        if (is_object($phraseKey)) {
            // already a phrase
            return $phraseKey;
        }

        if (is_string($phraseKey)) {
            return $this->phrase_provider->getPhrase($phraseKey);
        }

        if (
            count($phraseKey) === 2
            && isset($phraseKey[0], $phraseKey[1])
        ) {
            list($phraseId, $params) = $phraseKey[0];
        } else {
            $phraseId = array_shift($phraseKey);
            $params = $phraseKey;
        }

        return $this->phrase_provider->getPhrase($phraseId, $params);
    }
}