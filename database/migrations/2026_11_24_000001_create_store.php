<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $t) {
            $t->id();
            $t->string('kind');
            $t->string('slug');
            $t->text('data');
            $t->timestamps();
            $t->unique(['kind', 'slug']);
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->string('status')->default('pending');
            $t->text('customer');
            $t->text('lines');
            $t->unsignedBigInteger('total');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('entries');
    }
};
