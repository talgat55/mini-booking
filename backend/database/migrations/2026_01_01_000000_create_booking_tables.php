<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('starts_at')->unique();
            $table->unsignedBigInteger('ends_at');
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->unique()->constrained()->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_email', 254);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('slots');
    }
};
