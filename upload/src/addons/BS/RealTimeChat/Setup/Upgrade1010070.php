<?php

// 1.1.0

namespace BS\RealTimeChat\Setup;

use XF\Db\Schema\Create;

trait Upgrade1010070
{
    public function upgrade1010070Step1()
    {
        $this->schemaManager()->createTable('xf_bs_chat_long_polling_event', function (Create $table) {
            $table->addColumn('event_id', 'int')->autoIncrement();
            $table->addColumn('event_time', 'int')->setDefault(0);
            $table->addColumn('event_type', 'varchar', 30);
            $table->addColumn('event_data', 'mediumblob')->nullable();
            $table->addColumn('room_ids', 'blob');
            $table->addKey('event_time');
        });
    }
}
