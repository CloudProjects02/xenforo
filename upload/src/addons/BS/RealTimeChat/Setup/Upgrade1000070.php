<?php

// 1.0.0

namespace BS\RealTimeChat\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Upgrade1000070
{
    public function upgrade1000070Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_chat', function (Alter $table) {
            $table->renameTo('xf_bs_chat');
            $table->renameColumn('date', 'message_date');
        });

        $sm->alterTable('xf_chat_banned', function (Alter $table) {
            $table->renameTo('xf_bs_chat_banned');
        });
    }

    public function upgrade1000070Step2()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_bs_chat', function (Alter $table) {
            $table->changeColumn('message_date', 'bigint', 20);
            $table->addKey('message_date');
        });
    }

    public function upgrade1000070Step3()
    {
        $this->schemaManager()->createTable('xf_bs_chat_command', function (Create $table) {
            $table->addColumn('command_id', 'varbinary', 25)->primaryKey();
            $table->addColumn('command_regex', 'text');
            $table->addColumn('command_class', 'varchar', 300);
            $table->addColumn('active', 'tinyint', 3)->setDefault(1);
            $table->addColumn('addon_id', 'varbinary', 50);
            $table->addKey('active');
            $table->addKey('addon_id');
        });
    }

    public function upgrade1000070Step4()
    {
        $db = $this->db();

        $commands = [
            'clear'               => [
                'command_class' => 'BS\RealTimeChat\ChatCommand\Clear',
                'command_regex' => '/^\/clear$/i',
                'active'        => true
            ],
            'clear_user_messages' => [
                'command_class' => 'BS\RealTimeChat\ChatCommand\ClearUser',
                'command_regex' => '/^\/clear\s\[user=([0-9]*)]@.*\[\/user]$/iu',
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
