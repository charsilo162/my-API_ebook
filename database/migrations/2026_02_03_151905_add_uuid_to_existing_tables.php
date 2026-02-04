<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'books',
            'bookshops',
            'categories',
            'vendors',
            'orders',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // nullable first to avoid issues on existing rows
                $table->uuid('uuid')->nullable()->after('id')->unique();
            });

            // Populate UUIDs for existing records
            DB::table($table)->whereNull('uuid')->get()->each(function ($row) use ($table) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => Str::uuid()]);
            });

            // Make uuid NOT NULL after population
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'books',
            'bookshops',
            'categories',
            'vendors', 
            'orders',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
