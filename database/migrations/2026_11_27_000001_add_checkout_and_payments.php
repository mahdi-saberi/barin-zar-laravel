<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('mobile', 11)->nullable()->unique();
            $table->text('national_id')->nullable();
            $table->string('national_id_hash', 64)->nullable()->unique();
            $table->string('role', 24)->default('customer')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 16)->default('manual');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('manual_reference', 120)->nullable();
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->string('provider', 16)->primary();
            $table->boolean('enabled')->default(false);
            $table->text('credentials');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('provider', 16);
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('amount_rial');
            $table->unsignedBigInteger('fee_rial')->default(0);
            $table->text('credentials')->nullable();
            $table->text('token')->nullable();
            $table->text('return_data')->nullable();
            $table->string('bank_reference', 160)->nullable();
            $table->string('code', 60)->nullable();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['provider', 'bank_reference']);
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 32);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_gateways');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['gateway', 'expires_at', 'paid_at', 'shipping_address', 'manual_reference']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropUnique(['national_id_hash']);
            $table->dropIndex(['role']);
            $table->dropColumn(['mobile', 'national_id', 'national_id_hash', 'role']);
            // Keep the email column nullable so mobile-only accounts survive a rollback.
        });
    }
};
