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
        Schema::create('fundraising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('total_raised', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('featured_image')->nullable();
            $table->enum('campaign_category', ['Building', 'Missionary', 'Charity', 'General'])->nullable();
            $table->text('bank_account_info')->nullable();
            $table->enum('status', ['Draft', 'Active', 'Completed', 'Cancelled'])->default('Draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundraising_campaigns');
    }
};
