<?php

namespace XC\ExtendPokedex\Admin\View\Card;

use XF\Mvc\View;

class Find extends View {

    public function renderJson() {
        $results = [];
        $repo = \xf::app()->repository('apathy\Pokedex:Api');

        $href = $repo->findImageUrls(\xf::options()->apPdAssetSource, 'card');

        $size = "s";
        foreach ($this->params['cards'] AS $card) {



            //  $attributeString = $this->getAttributesAsString($templater, $attributes);
            $hrefAttr = htmlspecialchars($href);
            $codename = explode('-', $card->codename);
            $id = htmlspecialchars($codename[0]);
            $num = htmlspecialchars($codename[1]);
            $size = ($size == 's') ? 'small/' : 'large/';
            $size = htmlspecialchars($size);

            $results[] = [
                'id' => $card->codename,
                'iconHtml' => "<img src=\"{$hrefAttr}{$size}{$id}/{$num}.webp\" style='max-width: 4%;     margin-right: 7px;'/>",
                'text' => $card->codename,
            ];
        }

        return [
            'results' => $results,
            'q' => $this->params['q']
        ];
    }

}
