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
        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('Group name');
            $table->string('group_type', 100)->nullable()->comment('Group type: Kids, Youth, Adult, Ministry, etc.');
            $table->text('description')->nullable()->comment('Group description');
            $table->boolean('is_active')->default(true)->comment('Group active status');
            $table->foreignId('created_by')->constrained('users')->comment('User who created the group');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['is_active']);
            $table->index(['group_type']);
            $table->index(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_groups');
    }
};
