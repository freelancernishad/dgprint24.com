<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');

            $table->string('stripe_subscription_id')->nullable()->index(); // for recurring
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->dateTime('next_billing_date')->nullable(); // for recurring

            $table->decimal('original_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->json('plan_features')->nullable();

            $table->string('status')->default('pending'); // pending, active, cancelled, expired
            $table->string('billing_interval')->nullable(); // monthly, yearly, etc.
            $table->integer('billing_cycles_completed')->default(0); // for recurring

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_subscriptions');
    }
};
