<?php

namespace AddonsLab\Core\vB3;

use AddonsLab\Core\SessionProviderInterface;

// TODO this does not persist the data yet
class SessionProvider implements SessionProviderInterface
{
    /**
     * @return \vB_Session
     */
    protected function _getSession()
    {
        return $GLOBALS['vbulletin']->session;
    }

    /**
     * @return string|bool
     */
    public function getSessionId()
    {
        $session = $this->_getSession();
        return ($session && $session->vars['sessionhash']) ? $session->vars['sessionhash'] : false;
    }

    public function getFromSession($name)
    {
        $session = $this->_getSession();

        return $session && isset($session->vars[$name]) ? $session->vars[$name] : false;
    }

    public function saveToSession($name, $value)
    {
        $session = $this->_getSession();

        if ($session) {
            $session->vars[$name] = $value;
            return;
        }

        throw new \RuntimeException('Session is not started and can not be used');
    }

    public function deleteFromSession($name)
    {
        $session = $this->_getSession();
        if ($session) {
            unset($session->vars[$name]);
            return;
        }

        throw new \RuntimeException('Session is not started and can not be used');
    }

}