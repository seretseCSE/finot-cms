<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_parent_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade')->comment('Member ID');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('parent_name', 200)->comment('Parent/guardian name');
            $table->enum('relationship', ['Father', 'Mother', 'Guardian', 'GrandFather', 'GrandMother', 'Uncle', 'Brother', 'Aunt', 'Sister', 'Other'])->comment('Relationship to member');
            $table->string('phone', 20)->comment('Parent/guardian phone number');
            $table->boolean('is_external')->default(true)->comment('True if parent not in parents table');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['member_id', 'relationship']);
            $table->index(['parent_id']);
            $table->index(['is_external']);
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
