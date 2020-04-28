<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BaseModel extends Model
{
    public function getDateFormat()
    {
        return 'd-m-Y H:i:s.V';
    }

    public $timestamps  = false;
}
