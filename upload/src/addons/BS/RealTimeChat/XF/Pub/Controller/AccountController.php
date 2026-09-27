<?php

namespace BS\RealTimeChat\XF\Pub\Controller;

use BS\RealTimeChat\Pub\Controller\Concerns\Language;
use BS\RealTimeChat\Service\Translator;
use XF\Mvc\Reply\View;

class AccountController extends XFCP_AccountController
{
    use Language;

    protected function preferencesSaveProcess(\XF\Entity\User $visitor)
    {
        $form = parent::preferencesSaveProcess($visitor);

        $languageCode = $this->filter('user.rtc_language_code', 'str');

        if (\XF::visitor()->hasChatPermission('translateMessages')
            && Translator::isLanguageSupported($languageCode)
        ) {
            $form->basicEntitySave($visitor, [
                'rtc_language_code' => $languageCode
            ]);
        }

        return $form;
    }

    public function actionPreferences()
    {
        $reply = parent::actionPreferences();

        if (! ($reply instanceof View)) {
            return $reply;
        }

        if (\XF::visitor()->hasChatPermission('translateMessages')) {
            $reply->setParam('rtcLanguages', Translator::LANGUAGES);
            $reply->setParam('rtcLanguageCode', $this->getChatLanguageCode());
        }

        return $reply;
    }
}
