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
        Schema::create('member_education_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade')->comment('Member ID');
            $table->string('school_name', 200)->comment('School name');
            $table->string('education_level', 100)->comment('Education level');
            $table->string('education_department', 100)->comment('Education department/major');
            $table->boolean('is_current')->default(true)->comment('Currently studying here');
            $table->timestamps();
            
            // Indexes
            $table->index(['member_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_education_history');
    }
};
