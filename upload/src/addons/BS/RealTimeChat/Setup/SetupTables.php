<?php

namespace BS\RealTimeChat\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait SetupTables
{
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $closures) {
            $sm->alterTable($tableName, $closures['up']);
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $closures) {
            $sm->alterTable($tableName, $closures['down']);
        }
    }

    protected function getTables()
    {
        $tables = [];

        $tables['xf_bs_chat_message'] = static function (Create $table) {
            $table->addColumn('message_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('message', 'text');
            $table->addColumn('message_date', 'bigint', 20);
            $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('to_user_id', 'int')->setDefault(0);
            $table->addColumn('pm_user_id', 'int')->setDefault(0);
            $table->addColumn('room_id', 'int')
                ->nullable()
                ->setDefault(null);
            $table->addColumn('room_tag', 'varchar', 50)->setDefault('');
            $table->addColumn('type', 'varchar', 25)
                ->setDefault('bubble');

            $table->addColumn('has_been_read', 'tinyint', 3)
                ->unsigned()
                ->setDefault(0);

            $table->addColumn('reaction_score', 'int')
                ->unsigned(false)
                ->setDefault(0);
            $table->addColumn('reactions', 'blob')->nullable();
            $table->addColumn('reaction_users', 'blob');

            $table->addColumn('attach_count', 'int')
                ->setDefault(0);

            $table->addColumn('extra_data', 'json')->nullable();

            $table->addKey('user_id');
            $table->addKey('message_date');
            $table->addKey('to_user_id');
            $table->addKey('pm_user_id');
            $table->addKey('room_id');
            $table->addKey('room_tag');
            $table->addKey('type');
        };

        $tables['xf_bs_chat_ban'] = static function (Create $table) {
            $table->addColumn('ban_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('ban_user_id', 'int');
            $table->addColumn('date', 'int');
            $table->addColumn('unban_date', 'int');
            $table->addColumn('reason', 'varchar', 300)->nullable();
            $table->addColumn('room_id', 'int')
                ->nullable()
                ->setDefault(null);
            $table->addKey('room_id');
            $table->addKey('user_id');
            $table->addKey('ban_user_id');
        };

        $tables['xf_bs_chat_room'] = static function (Create $table) {
            $table->addColumn('room_id', 'int')->autoIncrement();
            $table->addColumn('type', 'varchar', 25)
                ->setDefault('member');
            $table->addColumn('tag', 'varchar', 50);
            $table->addColumn('description', 'text')->nullable();
            $table->addColumn('avatar_date', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('user_id', 'int')->unsigned()->nullable();
            $table->addColumn('members_count', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('members', 'json')->nullable();
            $table->addColumn('last_message_id', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('last_message_date', 'bigint', 20)
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('last_message_user_id', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('created_date', 'int')
                ->unsigned();

            $table->addColumn('pinned', 'tinyint')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('pin_order', 'int')
                ->unsigned()
                ->setDefault(0);

            $table->addColumn('allowed_replies', 'tinyint')
                ->unsigned()
                ->setDefault(1);

            $table->addColumn('wallpaper_date', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('wallpaper_options', 'json')->nullable();

            $table->addUniqueKey('tag');
            $table->addKey('user_id');
            $table->addKey('last_message_date');
            $table->addKey('created_date');

            $table->addKey('pinned');
            $table->addKey('pin_order');
        };

        $tables['xf_bs_chat_room_member'] = static function (Create $table) {
            $table->addColumn('member_id', 'int')->autoIncrement();
            $table->addColumn('room_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('invited_by_user_id', 'int')
                ->unsigned()
                ->nullable();
            $table->addColumn('invite_type', 'varchar', 25)
                ->setDefault('link');
            $table->addColumn('join_date', 'int')
                ->unsigned();
            $table->addColumn('last_reply_date', 'bigint', 20)
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('last_view_date', 'bigint', 20)
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('room_pinned', 'tinyint')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('room_pin_order', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('unread_count', 'int')
                ->unsigned()
                ->setDefault(0);

            $table->addColumn('room_wallpaper_date', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addColumn('room_wallpaper_options', 'json')->nullable();

            $table->addKey('room_id');
            $table->addKey('user_id');
            $table->addKey('invited_by_user_id');
            $table->addKey('join_date');
            $table->addKey('last_view_date');
            $table->addKey('room_pinned');
            $table->addKey('room_pin_order');
        };

        $tables['xf_bs_chat_room_link'] = static function (Create $table) {
            $table->addColumn('link_id', 'varchar', 50)->primaryKey();
            $table->addColumn('room_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned()->nullable();
            $table->addColumn('create_date', 'int')->unsigned();
            $table->addColumn('expire_date', 'int')->unsigned()->nullable();
            $table->addColumn('usage_count', 'int')
                ->unsigned()
                ->setDefault(0);
            $table->addKey('room_id');
            $table->addKey('user_id');
            $table->addKey('expire_date');
        };

        return $tables;
    }

    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_user'] = [
            'up' => static function (Alter $table) {
                $table->addColumn('rtc_language_code', 'varchar', 10)
                    ->setDefault('');
            },
            'down' => static function (Alter $table) {
                $table->dropColumns(['rtc_language_code']);
            },
        ];

        return $tables;
    }
}
