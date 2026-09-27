<?php

namespace BS\XFMessenger\Template;

class Templater
{
    public static function createRoomFormPreRender(
        \XF\Template\Templater $templater,
        &$type,
        &$template,
        &$name,
        array &$arguments,
        array &$globalVars
    ) {
        $arguments['maxRecipients'] = \XF::em()->create('XF:ConversationMaster')
            ->getMaximumAllowedRecipients();

        $arguments['draft'] = \XF\Draft::createFromKey('conversation');
    }

    public static function helperJsGlobalPreRender(
        \XF\Template\Templater $templater,
        &$type,
        &$template,
        &$name,
        array &$arguments,
        array &$globalVars
    ) {
        $cdnConfig = $globalVars['rtcDevsellCdn'] ?? [];

        $app = \XF::app();
        $visitor = \XF::visitor();
        $options = \XF::options();

        $canUseConversations = $visitor->canReceiveConversation() || $visitor->canStartConversation();
        $needCdnToLoad = $options->xfmEnableCrosspage
            || $options->xfmEnablePopup
            || preg_match('/^\/conversations\/?/', $app->request()->getRequestUri());

        $shouldLoadForMessenger = $canUseConversations && $needCdnToLoad;

        $cdnConfig['shouldLoad'] = ($cdnConfig['shouldLoad'] ?? false) || $shouldLoadForMessenger;

        $cdnConfig['popupModule'] = ($cdnConfig['popupModule'] ?? false) || $options->xfmEnablePopup;

        $globalVars['rtcDevsellCdn'] = $cdnConfig;
    }
}
