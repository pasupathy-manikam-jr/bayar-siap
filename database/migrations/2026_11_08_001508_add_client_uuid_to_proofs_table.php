<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Id the phone gives each photo, so the offline queue can retry a proof without creating a duplicate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proofs', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('milestone_id');
            $table->unique(['milestone_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('proofs', function (Blueprint $table) {
            $table->dropUnique(['milestone_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
