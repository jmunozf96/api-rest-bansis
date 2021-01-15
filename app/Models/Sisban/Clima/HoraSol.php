<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class HoraSol extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_horas_sol';

}
