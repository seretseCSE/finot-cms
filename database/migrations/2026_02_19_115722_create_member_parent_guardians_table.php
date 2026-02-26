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
        Schema::create('member_parent_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade')->comment('Member ID');
            $table->string('parent_name', 200)->comment('Parent/guardian name');
            $table->enum('relationship', ['Father', 'Mother', 'Guardian', 'GrandFather', 'GrandMother', 'Uncle', 'Brother', 'Aunt', 'Sister', 'Other'])->comment('Relationship to member');
            $table->string('phone', 20)->comment('Parent/guardian phone number');
            $table->timestamps();
            
            // Indexes
            $table->index(['member_id', 'relationship']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_parent_guardians');
    }
};
