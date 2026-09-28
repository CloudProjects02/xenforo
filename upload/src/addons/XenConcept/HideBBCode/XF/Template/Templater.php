<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\Template;

use XF\Entity\Post;

class Templater extends XFCP_Templater
{
    public function fnReaction($templater, &$escape, array $config)
    {
        /** @var Templater $templater */
        $response = parent::fnReaction($templater, $escape, $config);

        if (isset($config['content']) && $config['content'] instanceof Post && $config['content'])
        {
            $data = 'data-check-hide-post-id="' . $config['content']->post_id .'"';

            $regex = '/data-xf-init="[a-zA-Z0-9_]+"/';

            $response = preg_replace($regex, '$0 ' .$data, $response);
        }

        return $response;
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected function getHideBbCodeRepo()
    {
        return \XF::repository('XenConcept\HideBBCode:HideBbCode');
    }


}