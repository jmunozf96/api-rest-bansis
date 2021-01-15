<?php

namespace App\Models\Sisban\Clima;

use App\Models\BaseModel;

class Micrometro extends BaseModel
{
    protected $connection = 'sbn_pgsql';
    protected $table = 'cli_micrometro';

}
