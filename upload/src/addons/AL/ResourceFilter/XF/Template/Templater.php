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


namespace AL\ResourceFilter\XF\Template;

use AL\FilterFramework\FilterApp;
use XF\App;
use XF\Db\Exception;
use XF\Language;
use XF\Template\Template;

class  Templater extends XFCP_Templater
{
    public function __construct(App $app, Language $language, $compiledPath)
    {
        parent::__construct($app, $language, $compiledPath);

        $this->addFilter('rf_array_sub_replace', [$this, 'rf_filterReplaceArraySubItem']);
    }

    /*public function renderTemplate($template, array $params = [], $addDefaultParams = true)
    {
        $type = false;
        if (strpos($template, ':') !== false) {
            list($type, $templateName) = explode(':', $template, 2);
        } else {
            $templateName = $template;
        }

        if ($type === 'public' && $templateName === 'PAGE_CONTAINER')
        {
            if (\XF::options()->offsetExists('alrf_off_canvas_filter') && \XF::options()->offsetGet('alrf_off_canvas_filter'))
            {
                // add filter params passed to the page to the container
                // this will add only keys, that don't exist in the container
                $params += \AL\ResourceFilter\App::getContextProvider()->getPageContainerViewParams();
            }

            // licensing system
            $params['ResourceFilterCopyright'] = '';

            $addonName = 'Resource Filter by AddonsLab';

            $licenseValidationService = \AL\ResourceFilter\App::getLicenseValidationService('\AL\ResourceFilter\Licensing\Engine\Xf2');
            $checker = $licenseValidationService->getLicenseChecker();

            $licenseKey = \AL\ResourceFilter\App::getOptionProvider()->getOption('alrf_license_key');
            try {
                $licenseData = $checker->getLocalLicenseData($licenseKey);
            } catch (Exception $dbException) {
                // database exceptions are ignored on front-end
                return parent::renderTemplate($template, $params, $addDefaultParams);
            }


            if ($licenseData != false) {
                $message = '';
                if ($licenseData->isExpiredTrial()) {
                    $message = $licenseValidationService->getExpiredTrialMessage($addonName);
                } else if ($licenseData->isValid() === false) {
                    $message = $licenseValidationService->getInvalidLicenseMessage($addonName);
                } else if ($licenseData->hasBranding()) {
                    $message = $licenseValidationService->getBrandingMessage($addonName);
                }
            } else {
                $message = $licenseValidationService->getLicenseEmptyMessage($addonName);
            }

            if ($message) {
                $message = '<div>' . $message . '</div>';
            }

            $params['ResourceFilterCopyright'] = $message;
        }

        return parent::renderTemplate($template, $params, $addDefaultParams);
    }*/


    public function rf_filterReplaceArraySubItem($templater, $value, &$escape, $key, $from, $to = null, $subItemId = null)
    {
        return FilterApp::getActiveFilterHelper()->replaceSubFilter(
            $templater,
            $value,
            $escape,
            $key,
            $from,
            $to,
            $subItemId
        );
    }
}
