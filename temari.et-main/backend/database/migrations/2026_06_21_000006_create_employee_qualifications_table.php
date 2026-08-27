<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic credentials — a person holds many (BEd + MSc + PGDT …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('education_level', 50); // certificate|diploma|bachelor|master|phd|pgdt|other
            $table->string('field_of_study')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable(); // Gregorian
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qualifications');
    }
};
