<?php

namespace BS\RealTimeChat\Pub\Controller\Concerns;

trait RequestLimitations
{
    public function assertXhrOnly()
    {
        if (! $this->request->isXhr()) {
            throw $this->exception(
                $this->error(
                    \XF::phrase('rtc_action_available_via_xhr_only'),
                    405
                )
            );
        }
    }
}