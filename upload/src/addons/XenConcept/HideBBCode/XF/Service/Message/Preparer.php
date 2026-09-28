<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\Service\Message;;

class Preparer extends XFCP_Preparer
{

    protected $disabledHideTags;

    public function __construct(\XF\App $app, $context, \XF\Mvc\Entity\Entity $messageEntity = null)
    {
        parent::__construct($app, $context, $messageEntity);

        $this->disabledHideTags = $this->getHideBbCodeRepo()->getDisabledHideTagsForEntity($messageEntity);
    }

    protected function getBbCodeProcessor()
    {
        $processor = parent::getBbCodeProcessor();

        return $this->addLimitProcessorAction($processor);
    }

    protected function addLimitProcessorAction(\XF\BbCode\Processor $processor)
    {
        /** @var \XF\BbCode\ProcessorAction\LimitTags $limit */
        $limit = $this->app->bbCode()->processorAction('limit');

        $this->setupHideBbCodeLimits($limit);

        $processor->addProcessorAction('limit', $limit);

        if ($limit->hasDisabledTags())
        {
            $limit->setStripDisabled(false);
        }

        return $processor;
    }

    protected function setupHideBbCodeLimits(\XF\BbCode\ProcessorAction\LimitTags $limit)
    {
        foreach ($this->disabledHideTags AS $tag)
        {
            $limit->disableTag(strtolower($tag));
        }
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected function getHideBbCodeRepo()
    {
        return $this->repository('XenConcept\HideBBCode:HideBbCode');
    }
}