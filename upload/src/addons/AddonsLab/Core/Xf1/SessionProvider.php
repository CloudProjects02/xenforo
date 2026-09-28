<?php

namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\SessionProviderInterface;

class SessionProvider implements SessionProviderInterface
{
    protected function _getSession()
    {
        if (\XenForo_Application::isRegistered('session')) {
            return \XenForo_Application::getSession();
        }

        return false;
    }

    /**
     * @return string|bool
     */
    public function getSessionId()
    {
        $session = $this->_getSession();
        return $session ? $session->getSessionId() : false;
    }

    public function getFromSession($name)
    {
        $session = $this->_getSession();

        if ($session) {
            $sessionValue = $session->get($name);
            if ($sessionValue === false) {
                $sessionValue = null;
            }

            return $sessionValue;
        }

        return null;
    }

    public function saveToSession($name, $value)
    {
        $session = $this->_getSession();

        if ($session) {
            $session->set($name, $value);
            return;
        }

        throw new \RuntimeException('Session is not started and can not be used');
    }

    public function deleteFromSession($name)
    {
        $session = $this->_getSession();
        if ($session) {
            $session->set($name, false);
            return;
        }

        throw new \RuntimeException('Session is not started and can not be used');
    }

}