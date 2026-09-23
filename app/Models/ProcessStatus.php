<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessStatus extends Model
{
    protected $fillable = ['code', 'label', 'bucket', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
