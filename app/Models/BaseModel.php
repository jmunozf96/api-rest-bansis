<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    public function getDateFormat()
    {
        return 'd-m-Y H:i:s.v';
    }

    public $timestamps  = false;
}
