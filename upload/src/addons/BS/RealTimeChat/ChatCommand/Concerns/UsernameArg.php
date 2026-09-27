<?php

namespace BS\RealTimeChat\ChatCommand\Concerns;

trait UsernameArg
{
    protected function getUserFromUsernameArg(?string $usernameArg = null): ?\XF\Entity\User
    {
        if (!$usernameArg) {
            return null;
        }

        preg_match("/{$this->getUsernameGroupRegex($usernameArg)}/si", $usernameArg, $matches);

        if (isset($matches[2])) {
            $userId = (int)$matches[2];
            return $this->em()->find('XF:User', $userId);
        }

        return $this->em()->findOne('XF:User', [
            'username' => $usernameArg
        ]);
    }

    protected function buildUsernameCommandRegex(string $username): string
    {
        return "/^\/{$this->getName()}\s?{$this->getUsernameGroupRegex($username)},?\s?/si";
    }

    protected function getUsernameGroupRegex(string $username): string
    {
        $username = preg_quote($username, '/');
        return "(\[user=(\d+)].*?\[\/user]|$username)";
    }
}
