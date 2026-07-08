<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic subscriptions — a subscription belongs to any subject
 * (User for PRO Consultor, Hotel for PRO Empresa). Provider columns are
 * nullable so a payment gateway (Stripe / Mercado Pago) can be attached later
 * without a schema change.
 *
 * Sprint: monetization-hotelconnect-pro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Polymorphic owner: users | hotels
            $table->morphs('subscribable');

            $table->string('plan', 30);                 // App\Enums\SubscriptionPlan
            $table->string('status', 20)->default('active'); // App\Enums\SubscriptionStatus

            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = no expiry (manual/admin)
            $table->timestamp('cancelled_at')->nullable();

            // Payment gateway integration — populated by a future sprint
            $table->string('provider', 30)->nullable();          // 'stripe' | 'mercado_pago'
            $table->string('provider_subscription_id')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
