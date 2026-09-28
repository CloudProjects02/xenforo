<?php

namespace Olakunlevpn\PostThreadLimitDaily\XF\Entity;

class Thread extends XFCP_Thread
{
    /**
     * Additional validation when saving a thread
     *
     * @return array
     */
    protected function _preSave()
    {
        //TODO move some of the Thread create logic here
        $errors = parent::_preSave();

        return $errors;
    }
}
