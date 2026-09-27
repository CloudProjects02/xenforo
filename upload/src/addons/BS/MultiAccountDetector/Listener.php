<?php

namespace BS\MultiAccountDetector;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function appSetup(\XF\App $app)
    {
        $container = $app->container();

        $container['multiAccountCache'] = $app->fromRegistry(
            'multiAccountCache',
            function (\XF\Container $c) {
                return $c['em']->getRepository('BS\MultiAccountDetector:MultiAccount')->rebuildMultiAccountsCache();
            }
        );
    }

    public static function entityStructureUser(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns += [
            'mad_last_check' => ['type' => Entity::UINT, 'default' => 0]
        ];
        $structure->getters += [
            'fingerprints' => true,
            'evercookies' => true
        ];
        $structure->relations += [
            'MultiAccount' => [
                'entity' => 'BS\MultiAccountDetector:MultiAccount',
                'type' => Entity::TO_ONE,
                'conditions' => 'user_id'
            ],
            'Fingerprints' => [
                'entity' => 'BS\MultiAccountDetector:Fingerprint',
                'type' => Entity::TO_MANY,
                'conditions' => 'user_id'
            ],
            'Evercookies' => [
                'entity' => 'BS\MultiAccountDetector:Evercookie',
                'type' => Entity::TO_MANY,
                'conditions' => 'user_id'
            ]
        ];
    }

    public static function entityPostDeleteUser(\XF\Mvc\Entity\Entity $entity)
    {
        $db = $entity->db();

        $db->delete('xf_mad_user_evercookie', 'user_id = ?', $entity->user_id);
        $db->delete('xf_mad_user_fingerprint', 'user_id = ?', $entity->user_id);
        $db->delete('xf_mad_user_multi_account', 'user_id = ?', $entity->user_id);

        $entity->repository('BS\MultiAccountDetector:MultiAccount')->rebuildMultiAccountsCache();
    }

    public static function userMergeCombine(
        \XF\Entity\User $target,
        \XF\Entity\User $source,
        \XF\Service\User\Merge $mergeService
    ) {
        $db = $target->db();

        $db->update('xf_mad_user_evercookie', ['user_id' => $target->user_id], 'user_id = ?', $source->user_id);
        $db->update('xf_mad_user_fingerprint', ['user_id' => $target->user_id], 'user_id = ?', $source->user_id);
        $db->update('xf_mad_user_multi_account', ['user_id' => $target->user_id], 'user_id = ?', $source->user_id);
    }
}