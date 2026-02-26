<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->integer('file_size_kb');
            $table->string('file_type', 50);
            $table->text('description')->nullable();
            $table->text('tags')->nullable();
            $table->date('document_date')->nullable();
            $table->enum('visibility', ['Public', 'Members Only', 'Department Only'])->default('Department Only');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('visibility');
            $table->index('department_id');
            $table->index('uploaded_by');
            $table->index('document_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
