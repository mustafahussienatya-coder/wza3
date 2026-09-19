<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('vehicle_number')->nullable()->after('status');
            $table->string('vehicle_type')->nullable()->index()->after('vehicle_number');
            $table->string('national_id_photo_front')->nullable()->after('vehicle_type');
            $table->string('national_id_photo_back')->nullable()->after('national_id_photo_front');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn(['vehicle_number', 'vehicle_type', 'national_id_photo_front', 'national_id_photo_back']);
        });
    }
};
