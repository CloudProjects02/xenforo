<?php

namespace Olakunlevpn\PostThreadLimitDaily\XF\Entity;

class Post extends XFCP_Post
{
    /**
     * Additional validation when saving a post
     *
     * @return array
     */
    protected function _preSave()
    {
        //TODO move some of the Post create logic here
        $errors = parent::_preSave();
        return $errors;
    }
}
