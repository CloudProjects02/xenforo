<?php

// 2.0.0

namespace BS\RealTimeChat\Setup;

use BS\RealTimeChat\Utils\RoomTag;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Upgrade2000070
{
    public function upgrade2000070Step1()
    {
        $this->schemaManager()->dropTable('xf_bs_chat_long_polling_event');
    }

    public function upgrade2000070Step2()
    {
        $sm = $this->schemaManager();
        $sm->createTable(
            'xf_bs_chat_room',
            static function (Create $table) {
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
            }
        );
        $sm->createTable(
            'xf_bs_chat_room_member',
            static function (Create $table) {
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
            }
        );
        $sm->createTable(
            'xf_bs_chat_room_link',
            static function (Create $table) {
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
            }
        );
    }

    public function upgrade2000070Step3()
    {
        $sm = $this->schemaManager();
        $sm->renameTable('xf_bs_chat', 'xf_bs_chat_message');
        $sm->renameTable('xf_bs_chat_banned', 'xf_bs_chat_ban');
    }

    public function upgrade2000070Step4()
    {
        $sm = $this->schemaManager();
        $sm->alterTable(
            'xf_bs_chat_message',
            static function (Alter $table) {
                $table->addColumn('room_id', 'int')
                    ->nullable()
                    ->setDefault(null);
                $table->addColumn('room_tag', 'varchar', 50)
                    ->setDefault('');
                $table->addColumn('type', 'varchar', 25)
                    ->setDefault('bubble');
                $table->addKey('room_id');
                $table->addKey('room_tag');
                $table->addKey('type');

                $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);

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
            }
        );
    }

    public function upgrade2000070Step5()
    {
        $sm = $this->schemaManager();
        $sm->alterTable(
            'xf_bs_chat_ban',
            static function (Alter $table) {
                $table->renameColumn('banned_id', 'ban_id');
                $table->renameColumn('banned_user_id', 'ban_user_id');
                $table->addColumn('room_id', 'int')
                    ->nullable()
                    ->setDefault(null);
                $table->addKey('room_id');
            }
        );
    }

    public function upgrade2000070Step6()
    {
        $this->schemaManager()->dropTable('xf_bs_chat_command');
    }

    public function upgrade2000070Step7()
    {
        $db = $this->db();
        // insert public room
        $db->insert(
            'xf_bs_chat_room',
            [
                'type' => 'public',
                'tag' => RoomTag::DEFAULT_TAG,
                'description' => '',
                'avatar_date' => 0,
                'members_count' => 0,
                'last_message_id' => 0,
                'last_message_date' => 0,
                'last_message_user_id' => 0,
                'pinned' => 1,
                'pin_order' => 0,
                'created_date' => \XF::$time,
            ]
        );

        $db->update(
            'xf_bs_chat_message',
            [
                'room_id' => 1,
                'room_tag' => RoomTag::DEFAULT_TAG,
            ],
            '1'
        );

        $db->update(
            'xf_bs_chat_ban',
            [
                'room_id' => 1,
            ],
            '1'
        );
    }

    public function upgrade2000070Step8()
    {
        $this->schemaManager()->alterTable('xf_user', function (Alter $table) {
            $table->addColumn('rtc_language_code', 'varchar', 10)
                ->setDefault('');
        });
    }
}
