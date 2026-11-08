<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Live notifications (ContractorAlert): each contractor listens on their own private channel.
// web = session (browser), sanctum = API token (phones).
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id, ['guards' => ['web', 'sanctum']]);
