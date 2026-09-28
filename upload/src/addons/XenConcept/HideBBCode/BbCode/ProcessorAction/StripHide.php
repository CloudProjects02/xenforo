<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\BbCode\ProcessorAction;

use XF\App;
use XF\BbCode\ProcessorAction\FiltererInterface;
use XF\BbCode\ProcessorAction\FiltererHooks;
use XF\Str\Formatter;

class StripHide implements FiltererInterface
{
    /**
     * @var Formatter
     */
    protected $formatter;

    /**
     * Constructor
     * StripHide constructor.
     * @param Formatter $formatter
     */
    public function __construct(Formatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Add hooks to filter hide tags.
     * @param FiltererHooks $hooks
     */
    public function addFiltererHooks(FiltererHooks $hooks)
    {
        $hooks->addTagHook('hide', 'filterHide');
        $hooks->addTagHook('hidereply', 'filterHide');
        $hooks->addTagHook('hideposts', 'filterHide');
        $hooks->addTagHook('hidereact', 'filterHide');
        $hooks->addTagHook('hidereplyreact', 'filterHide');
        $hooks->addTagHook('hidereplyorreact', 'filterHide');
        $hooks->addTagHook('hideshowtogroups', 'filterHide');
        $hooks->addTagHook('hideuser', 'filterHide');
        $hooks->addTagHook('hidetrophy', 'filterHide');
        $hooks->addTagHook('hidereactscore', 'filterHide');
    }

    /**
     * Replace hide tags with message.
     * @param $tag
     * @param $function
     * @return string
     */
    public function filterHide($tag, $function)
    {
        return '[B][' . \XF::phrase('xc_hide_bbcode_hidden_content') . '][/B]';
    }

    /**
     * @param App $app
     * @return static
     */
    public static function factory(App $app)
    {
        return new static($app->stringFormatter());
    }
}