<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $variation_order_id
 * @property string $path
 * @property string $sha256
 * @property int $bytes
 */
class VoAttachment extends Model
{
    protected $guarded = ['id'];
}
