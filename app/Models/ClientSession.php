<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int $job_id
 * @property string $phone
 * @property string|null $otp_hash
 * @property Carbon|null $otp_expires_at
 * @property int $otp_attempts
 * @property string|null $device_token
 * @property Carbon|null $verified_at
 * @property-read Client $client
 * @property-read Job $job
 */
class ClientSession extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['otp_hash', 'device_token'];

    protected function casts(): array
    {
        return ['otp_expires_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
