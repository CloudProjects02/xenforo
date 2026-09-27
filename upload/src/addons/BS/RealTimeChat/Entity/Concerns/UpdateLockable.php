<?php

namespace BS\RealTimeChat\Entity\Concerns;

use BS\RealTimeChat\DB;

trait UpdateLockable
{
    public function lockForUpdate()
    {
        if (!$this->ensureLockable()) {
            return false;
        }

        $identifiers = $this->getIdentifierValues();

        $identifiersWhere = '';

        foreach ($identifiers as $identifier => $value) {
            $identifiersWhere .= "{$identifier} = ? AND ";
        }

        $identifiersWhere = rtrim($identifiersWhere, ' AND ');

        $this->db()->query("
            SELECT *
            FROM {$this->structure()->table}
            WHERE $identifiersWhere
            FOR UPDATE
        ", $identifiers);

        return true;
    }

    public function ensureLockable()
    {
        $lockable = $this->isUpdate();

        // Need primary key to lock the row
        if (! $lockable) {
            DB::repeatOnDeadlock(function () use (&$lockable) {
                $lockable = $this->save();
            });
        }

        return $lockable;
    }
}
