<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('deposits', 'depositor_account')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->string('depositor_account', 50)->nullable()->after('depositor_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('depositor_account');
        });
    }
};
