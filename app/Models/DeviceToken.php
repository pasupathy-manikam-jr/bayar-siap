<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $platform
 * @property string $token
 */
class DeviceToken extends Model
{
    protected $guarded = ['id'];
}
