<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $locale
 */
class Client extends Model implements HasLocalePreference
{
    use BelongsToCompany, Notifiable;

    protected $guarded = ['id'];

    /** @return HasMany<Job, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /** Language for their texts, emails and pushes (null = the app default). */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }
}
