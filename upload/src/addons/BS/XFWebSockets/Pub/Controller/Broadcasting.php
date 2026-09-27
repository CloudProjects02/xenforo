<?php

namespace BS\XFWebSockets\Pub\Controller;

use BS\XFWebSockets\Broadcast;
use BS\XFWebSockets\Utils\GuestVisitor;
use Pusher\Pusher;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;

class Broadcasting extends AbstractController
{
    use Concerns\BroadcasterReflection;

    public function actionAuth()
    {
        $this->assertPostOnly();
        $this->setResponseType('json');

        $visitor = \XF::visitor();

        if (! $visitor->hasPermission('websockets', 'use')) {
            return $this->noPermission();
        }

        $pusher = $this->assertPusher();

        $channelName = $this->filter('channel_name', 'str');
        $normalizedChannelName = $this->normalizeChannelName($channelName);
        [
            'pattern' => $pattern,
            'channel' => $channel
        ] = $this->assertValidChannelOptions($normalizedChannelName);

        $socketId = $this->assertSocket();

        $callback = [$channel, 'join'];
        if (! is_callable($callback)) {
            return $this->noPermission();
        }

        $parameters = $this->extractAuthParameters($pattern, $normalizedChannelName);
        $result = $callback($visitor, ...$parameters);
        if ($result === false) {
            return $this->noPermission();
        }

        return $this->validAuthenticationResponse(
            $result,
            $pusher,
            $channelName,
            $socketId
        );
    }

    protected function validAuthenticationResponse(
        $result,
        Pusher $pusher,
        string $channelName,
        string $socketId
    ) {
        $visitor = \XF::visitor();

        if (mb_strpos($channelName, 'private') === 0) {
            return $this->decodePusherResponse(
                $pusher->authorizeChannel($channelName, $socketId)
            );
        }

        $userId = $visitor->user_id ?: GuestVisitor::userId($this->request->getIp());
        $userInfo = is_array($result)
            ? $result
            : [
                'id' => $userId,
                'name' => $visitor->username ?: 'ANON/'.$userId,
            ];

        return $this->decodePusherResponse(
            $pusher->authorizePresenceChannel(
                $channelName,
                $socketId,
                $userId,
                $userInfo
            )
        );
    }

    protected function decodePusherResponse($response)
    {
        $view = $this->view('BS\XFWebSockets:Broadcasting\Jsonp');
        $view->setJsonParams(json_decode($response, true));

        return $view;
    }

    public function actionUserAuth()
    {
        $this->assertRegistrationRequired();

        $this->setResponseType('json');

        $visitor = \XF::visitor();

        $pusher = $this->assertPusher();
        $socketId = $this->assertSocket();

        $settings = $pusher->getSettings();
        $user = $visitor->toApiResult()->render();
        $user->id = $visitor->user_id;
        unset($user->user_id);
        $encodedUser = json_encode($user);
        $decodedString = "{$socketId}::user::{$encodedUser}";

        $auth = $settings['auth_key'].':'.hash_hmac(
            'sha256', $decodedString, $settings['secret']
        );

        $response = $this->view('BS\XFWebSockets:Broadcasting\Auth');
        $response->setJsonParams([
            'auth' => $auth,
            'user_data' => $encodedUser,
        ]);

        return $response;
    }

    public function actionRefreshCsrf()
    {
        $app = $this->app;
        $view = $this->view();

        if ($this->validateCsrfToken()) {
            // csrf token is already valid, no need to refresh
            $csrf = $this->filter('_xfToken', 'str')
                ?: $this->request->getServer('HTTP_X_XF_CSRF_TOKEN', '');
            $view->setJsonParams(compact('csrf'));
            return $view;
        }

        $token = \XF::generateRandomString(16);
        $app->updateCsrfCookie($token);

        $validator = $app['csrf.validator'];
        $csrf = \XF::$time.','.$validator($token, \XF::$time);

        $view->setJsonParams(compact('csrf'));
        return $view;
    }

    /**
     * @return Pusher
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertPusher()
    {
        $pusher = $this->app['pusher'];
        if (! $pusher) {
            throw $this->exception($this->noPermission());
        }

        return $pusher;
    }

    /**
     * @param  string  $name
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertValidChannelOptions(string $name): array
    {
        $channelOptions = Broadcast::getChannelOptions($name);
        if (! $channelOptions) {
            throw $this->exception($this->noPermission());
        }

        return $channelOptions;
    }

    /**
     * @return string
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertSocket()
    {
        $socketId = $this->filter('socket_id', 'str');
        if (! $socketId) {
            throw $this->exception($this->noPermission());
        }

        return $socketId;
    }

    public function assertViewingPermissions($action)
    {
        // We don't need to check permissions here
        return true;
    }

    protected function canUpdateSessionActivity(
        $action,
        ParameterBag $params,
        AbstractReply &$reply,
        &$viewState
    ) {
        return false;
    }

    public function checkCsrfIfNeeded($action, ParameterBag $params)
    {
        if ($action === 'RefreshCsrf') {
            return;
        }

        parent::checkCsrfIfNeeded($action, $params);
    }

    public function checkTfaRedirect()
    {
        // We don't need to check TFA here
    }

    public function assertTfaRequirement($action)
    {
        // We don't need to check TFA here
    }

    public function assertPolicyAcceptance($action)
    {
        // We don't need to check policy acceptance here
    }
}
