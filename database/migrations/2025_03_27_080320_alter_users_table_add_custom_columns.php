<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number', 20)->after('name');
            }
            if (!Schema::hasColumn('users', 'hometown')) {
                $table->string('hometown', 100)->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('users', 'identity_card')) {
                $table->string('identity_card', 20)->nullable()->after('hometown');
            }
            if (!Schema::hasColumn('users', 'vehicle_plate')) {
                $table->string('vehicle_plate', 20)->nullable()->after('identity_card');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 10)->default('active')->after('vehicle_plate');
            }
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->after('status')->constrained('roles');
            }
            if (!Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url', 255)->nullable()->after('role_id');
            }
            if (!Schema::hasColumn('users', 'notification_preferences')) {
                $table->text('notification_preferences')->nullable()->after('avatar_url');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'notification_preferences')) {
                $table->dropColumn('notification_preferences');
            }
            if (Schema::hasColumn('users', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('users', 'vehicle_plate')) {
                $table->dropColumn('vehicle_plate');
            }
            if (Schema::hasColumn('users', 'identity_card')) {
                $table->dropColumn('identity_card');
            }
            if (Schema::hasColumn('users', 'hometown')) {
                $table->dropColumn('hometown');
            }
            if (Schema::hasColumn('users', 'phone_number')) {
                $table->dropColumn('phone_number');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
