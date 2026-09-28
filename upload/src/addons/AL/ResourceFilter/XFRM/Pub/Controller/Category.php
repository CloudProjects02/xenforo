<?php
/** 
* @package [AddonsLab] Resource Filter
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 3.6.1
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\ResourceFilter\XFRM\Pub\Controller;

use AL\ResourceFilter\App;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class  Category extends XFCP_Category
{
    public function actionIndex(ParameterBag $params)
    {
        // check for older filter name and redirect to the new URL
        if ($filterData = $this->filter('resource_fields', 'array'))
        {
            $newPrefix = App::getContentTypeProvider()->getFilterName();

            $fullUrl = $this->request()->getFullRequestUri();

            $fullUrl = str_ireplace('resource_fields[', $newPrefix . '[', $fullUrl);

            return $this->redirect(
                $fullUrl, null, Redirect::PERMANENT
            );
        }

        $reply = parent::actionIndex($params);

        if ($reply instanceof View)
        {
            // this is needed for the widget on resource home page
            /** @var \XFRM\ControllerPlugin\Overview $overviewPlugin */
            $overviewPlugin = $this->plugin('XFRM:Overview');

            // we have already extended filter plugin to provide all arguments we need
            $filterReply = $overviewPlugin->actionFilters($reply->getParam('category'));

            if ($filterReply instanceof View)
            {
                $filterParams = $filterReply->getParams();

                $filterInput = $overviewPlugin->getResourceFilterInput();
                if (empty($filterInput['order']))
                {
                    unset($filterParams['filters']['order']);
                    unset($filterParams['filters']['direction']);
                }

                $filterParams['total'] = $reply->getParam('total');
                $filterParams['baseLinkPathClearButton'] = 'resources/categories';

                // we will need the params in the widget
                App::getContextProvider()->setContextParams(
                    $filterParams, true
                );

                // set the params in the main context as well, as it will be used by the form above the list
                $reply->setParams($filterParams);
            }
        }

        return $reply;
    }

    public function actionLoadSelectionOptions()
    {
        $this->setResponseType('json');

        $page = max(1, $this->filter('page', 'uint'));
        $fieldId = str_replace(App::getContentTypeProvider()->getFilterName() . '[', '', $this->filter('select_name', 'str'));
        $fieldId = trim($fieldId, '[]');
        $search = $this->filter('search', 'str');

        $data = App::getContextProvider()->getFieldOptionSuggestions(
            $page,
            $fieldId,
            $search
        );

        return $this->view('AL\ResourceFilter:OptionList', '', [
            'data' => $data,
        ]);
    }
}
