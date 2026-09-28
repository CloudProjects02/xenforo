<?php
namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\PhraseProviderInterface;

class Xf2PhraseProvider implements PhraseProviderInterface
{
    public function getPhrase($phraseId, $params = array())
    {
        return \XF::phrase($phraseId, $params);
    }
}