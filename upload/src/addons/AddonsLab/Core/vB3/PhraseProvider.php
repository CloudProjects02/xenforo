<?php

namespace AddonsLab\Core\vB3;

use AddonsLab\Core\PhraseProviderInterface;

class PhraseProvider implements PhraseProviderInterface
{
    public function getPhrase($phraseId, $params = array())
    {
        $phrase = $GLOBALS['vbphrase'];

        if (!empty($params)) {
            $params = array_merge(array($phrase), $params);
            return call_user_func_array('construct_phrase', $params);
        }
        
        return $phrase;
    }
}