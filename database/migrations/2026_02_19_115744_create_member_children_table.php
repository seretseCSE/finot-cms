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
        Schema::create('member_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade')->comment('Member ID');
            $table->string('child_name', 200)->comment('Child name');
            $table->integer('birth_order')->comment('Birth order among siblings');
            $table->timestamps();
            
            // Indexes
            $table->index(['member_id', 'birth_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_children');
    }
};
