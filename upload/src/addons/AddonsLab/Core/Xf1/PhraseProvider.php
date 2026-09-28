<?php
namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\PhraseProviderInterface;

class PhraseProvider implements PhraseProviderInterface
{
    public function getPhrase($phraseId, $params = array())
    {
        return new \XenForo_Phrase($phraseId, $params);
    }
}