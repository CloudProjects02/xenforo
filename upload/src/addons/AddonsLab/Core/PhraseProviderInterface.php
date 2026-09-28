<?php
namespace AddonsLab\Core;

interface PhraseProviderInterface
{
    public function getPhrase($phraseId, $params=array());
}