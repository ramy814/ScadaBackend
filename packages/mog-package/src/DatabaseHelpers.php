<?php

namespace Mog\ProjectHelper;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseHelpers
{
    public static function get_seq_next_val($seq_name)
    {
        return DB::selectOne("SELECT $seq_name.nextval FROM dual")->nextval;
    }

    public static function get_date_ar_name($date)
    {
        $date = Carbon::parse($date)->format('d-m-Y');

        return DB::selectOne("SELECT to_char(to_date('$date', 'dd-mm-yyyy'), 'Day', 'nls_date_language = arabic') as day_name FROM dual")->day_name;
    }

    public static function get_name($id_num, $separator = ' ', $field = 'full_name') {
        return DB::selectOne("SELECT misc.get_name(?, ?, ?) res from dual;", [
            $id_num, $separator, $field
        ])->res;
    }

    public static function get_death_status($id_num)
    {
        return self::get_name($id_num, 'dead_date');
    }

}
