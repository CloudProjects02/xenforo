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


namespace AL\ResourceFilter\Listener;

class TemplaterTemplatePreRender
{

    public static function listen(\XF\Template\Templater $templater, &$type, &$template, array &$params)
    {
        if ($type === 'public' && $template === 'PAGE_CONTAINER')
        {
            if (\XF::options()->offsetExists('alrf_off_canvas_filter') && \XF::options()->offsetGet('alrf_off_canvas_filter'))
            {
                // add filter params passed to the page to the container
                // this will add only keys, that don't exist in the container
                $params += \AL\ResourceFilter\App::getContextProvider()->getPageContainerViewParams();
            }

            $licenseValidationService = \AL\ResourceFilter\App::getLicenseValidationService('\AL\ResourceFilter\Licensing\Engine\Xf2');

            // licensing system
            $params['ResourceFilterCopyright'] = $licenseValidationService->getProductLicenseMessage(
                \AL\ResourceFilter\App::getOptionProvider()->getOption('alrf_license_key'),
                'alrf',
                'Resource Filter by AddonsLab'
            );
            $params['ResourceFilterCopyright'] = $params['ResourceFilterCopyright'] ? "<div>{$params['ResourceFilterCopyright']}</div>" : '';
        }
    }
}

