<?php

namespace MMO\CoreLib\Template;

use Carbon\Carbon;
use MMO\CoreLib\Util\Plural;

class TemplaterSetup
{
    public function funcMclDiffForHuman($templater, &$escape, $date, array $options = [])
    {
        $language = \XF::language();
        $carbon = Carbon::createFromTimestamp($date, $language->getTimeZone())
            ->locale(substr($language->getLanguageCode(), 0, 2));

        return $carbon->diffForHumans($options);
    }

    public function funcMclPhrasePlural($templater, &$escape, $phrase, $number)
    {
        return Plural::choice($phrase, $number);
    }
}