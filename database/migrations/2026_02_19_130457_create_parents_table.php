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
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 200)->comment('Parent full name');
            $table->string('phone', 20)->unique()->comment('Unique parent phone number');
            $table->string('relationship_type', 100)->nullable()->comment('Primary relationship type (contextual)');
            $table->integer('member_count')->default(0)->comment('Computed count of linked members');
            $table->boolean('is_active')->default(true)->comment('Parent active status');
            $table->text('notes')->nullable()->comment('Additional notes about parent');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['phone']);
            $table->index(['is_active']);
            $table->index(['full_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
