<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BaseModel extends Model
{
    public function getDateFormat()
    {
        return config('constants.format_date');
    }

    public $timestamps  = false;
}
