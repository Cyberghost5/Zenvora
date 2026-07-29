<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type VARCHAR(40) NOT NULL");
    }

    public function down(): void
    {
        // No-op
    }
};
