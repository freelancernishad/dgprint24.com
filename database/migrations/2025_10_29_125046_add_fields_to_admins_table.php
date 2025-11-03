<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('username');
            $table->string('user_id')->nullable()->after('role');
            $table->string('profile_picture')->nullable()->after('user_id');
            $table->string('phone_number')->nullable()->after('profile_picture');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('phone_number');
            $table->date('date_of_birth')->nullable()->after('status');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->string('driving_license')->nullable()->after('gender');
            $table->string('work_place')->nullable()->after('driving_license');
            $table->timestamp('last_login_at')->nullable()->after('work_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'user_id',
                'profile_picture',
                'phone_number',
                'status',
                'date_of_birth',
                'gender',
                'driving_license',
                'work_place',
                'last_login_at'
            ]);
        });
    }
};
