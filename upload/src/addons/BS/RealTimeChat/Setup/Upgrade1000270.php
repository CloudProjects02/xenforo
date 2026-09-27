<?php

// 1.0.2

namespace BS\RealTimeChat\Setup;

use BS\RealTimeChat\ChatCommand\Pm;
use BS\RealTimeChat\ChatCommand\To;
use XF\Db\Schema\Alter;

trait Upgrade1000270
{
    public function upgrade1000270Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_bs_chat', function (Alter $table) {
            $table->addColumn('to_user_id', 'int')->setDefault(0);
            $table->addColumn('pm_user_id', 'int')->setDefault(0);
            $table->addKey('to_user_id');
            $table->addKey('pm_user_id');
        });
    }

    public function upgrade1000270Step2()
    {
        $this->db()->update('xf_bs_chat_command', [
            'command_regex' => '/^\/clear\s\[user=([0-9]*)].*\[\/user]$/iu'
        ], 'command_id = \'clear_user_messages\'');
    }

    public function upgrade1000270Step3()
    {
        $db = $this->db();

        $commands = [
            'to' => [
                'command_class' => To::class,
                'command_regex' => '/^\/to\s\[user=([0-9]*)].*\[\/user]/iu',
                'active'        => true
            ],
            'pm' => [
                'command_class' => Pm::class,
                'command_regex' => '/^\/pm\s\[user=([0-9]*)].*\[\/user]/iu',
                'active'        => true
            ]
        ];

        foreach ($commands as $commandId => $command) {
            $insert = $command + [
                'command_id' => $commandId,
                'addon_id'   => 'BS/RealTimeChat'
            ];

            $db->insert('xf_bs_chat_command', $insert);
        }
    }
}
