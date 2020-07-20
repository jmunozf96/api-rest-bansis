<?php

namespace App\Models\Sisban\Primo;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Cosecha extends BaseModel
{
    protected $connection = "SISBAN";
    protected $table = 'cosecha_primo';
}
