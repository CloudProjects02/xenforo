<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\SubContainer;

class BbCode extends XFCP_BbCode
{

    public function processor()
    {
        $processor = parent::processor();

        if (\XenConcept\HideBBCode\Listener\BbCode::$addStripHideProcessor)
        {
            $processor->addProcessorAction('hide', $this->processorAction('hide'));
        }

        return $processor;
    }

}