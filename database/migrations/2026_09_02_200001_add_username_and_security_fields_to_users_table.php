<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->integer('failed_login_attempts')->default(0)->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });

        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'must_change_password', 'failed_login_attempts', 'locked_until']);
        });
    }
};
