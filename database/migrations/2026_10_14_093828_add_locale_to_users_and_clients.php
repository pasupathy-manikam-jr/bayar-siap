<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chosen interface language (en / ms / zh), so texts and emails reach each person in their language. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->nullable()->after('role'));
        Schema::table('clients', fn (Blueprint $table) => $table->string('locale', 5)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
