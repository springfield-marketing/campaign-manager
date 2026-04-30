<?php

namespace App\Modules\IVR\Support;

class BillableDuration
{
    public static function minutes(int $durationSeconds): int
    {
        if ($durationSeconds <= 0) {
            return 0;
        }

        if ($durationSeconds <= 60) {
            return 1;
        }

        return (int) ceil($durationSeconds / 60);
    }
}
