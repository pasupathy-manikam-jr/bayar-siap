<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** File sizes, so plan storage limits (Free 5 GB, Solo 50 GB) can be enforced without listing the bucket. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['proofs', 'vo_attachments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('bytes')->default(0)->after('sha256'));

            DB::table($table)->whereNotNull('path')->orderBy('id')->each(function (object $row) use ($table) {
                $path = (string) $row->path;
                DB::table($table)->where('id', $row->id)->update(['bytes' => Storage::exists($path) ? Storage::size($path) : 0]);
            });
        }
    }

    public function down(): void
    {
        foreach (['proofs', 'vo_attachments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('bytes'));
        }
    }
};
