<?php

// 1.0.0 BETA 2

namespace BS\RealTimeChat\Setup;

use XF\Db\Schema\Create;

trait Upgrade1000032
{
    public function upgrade1000032Step1()
    {
        $this->schemaManager()->createTable('xf_chat_banned', function (Create $table) {
            $table->addColumn('banned_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('banned_user_id', 'int');
            $table->addColumn('date', 'int');
            $table->addColumn('unban_date', 'int');
            $table->addColumn('reason', 'varchar', 300)->nullable();
            $table->addKey('user_id');
            $table->addKey('banned_user_id');
        });
    }
}
