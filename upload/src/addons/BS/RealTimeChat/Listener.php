<?php

namespace BS\RealTimeChat;

use BS\RealTimeChat\Broadcasting\ChatChannel;
use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use XF\BbCode\RuleSet;
use XF\Entity\StyleProperty;
use XF\Mvc\Entity\Entity;

class Listener
{
    public static function appSetup(\XF\App $app)
    {
        $app->container()->set(
            'rtcThemes',
            $app->fromRegistry(
                'rtcThemes',
                function (\XF\Container $c) {
                    return $c['em']->getRepository('BS\RealTimeChat:Theme')->rebuildThemesCache();
                }
            )
        );
    }

    public static function broadcastChannels(array &$channels)
    {
        $channels['Chat'] = ChatChannel::class;
        $channels['ChatRoom.{tag}'] = ChatRoomChannel::class;
    }

    public static function entityPreSaveStyleProperty(StyleProperty $property)
    {
        if ($property->property_name !== 'rtcThemes') {
            return;
        }

        $themes = $property->property_value;

        if (empty($themes)) {
            return;
        }

        // all settings must be filled
        $themes = array_filter($themes, static function ($theme) {
            foreach ($theme as $setting) {
                if (empty($setting)) {
                    return false;
                }
            }

            return true;
        });

        $property->property_value = array_values($themes);

        // Clear cache
        \XF::registry()->offsetUnset('rtcThemes');
    }

    public static function entityPostDeleteUser(Entity $user)
    {
        $user->db()->delete('xf_bs_chat_message', 'user_id = ?', $user->user_id);
    }

    public static function bbCodeRules(RuleSet $ruleSet, $context, $subContext)
    {
        // handle all bb codes in system messages
        if ($subContext === 'system_message') {
            return;
        }

        $enabledBbCodes = array_keys(array_filter(\XF::options()->realTimeChatEnabledBbCodes));

        // enable user mentions
        $enabledBbCodes[] = 'user';

        $bbCodes = $ruleSet->getTags();

        foreach ($bbCodes as $bbCode => $options) {
            if (in_array($bbCode, $enabledBbCodes, true)) {
                continue;
            }

            $ruleSet->removeTag($bbCode);
        }
    }
}
