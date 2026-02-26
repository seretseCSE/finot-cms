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
        Schema::create('members', function (Blueprint $table) {
            // Primary fields
            $table->id();
            $table->string('member_code', 10)->unique()->comment('Auto-generated member code in M-000001 format');
            
            // Member classification
            $table->enum('member_type', ['Kids', 'Youth', 'Adult'])->comment('Type of member: Kids, Youth, or Adult');
            $table->enum('status', ['Draft', 'Member', 'Active', 'Former'])->default('Draft')->comment('Current status of the member');
            
            // Personal information
            $table->string('title', 50)->comment('Title: Mr/Mrs/Dr/Dn/etc.');
            $table->string('first_name', 100)->comment('First name of the member');
            $table->string('father_name', 100)->comment('Father\'s name');
            $table->string('grandfather_name', 100)->comment('Grandfather\'s name');
            $table->string('mother_name', 100)->comment('Mother\'s name');
            $table->date('date_of_birth')->comment('Date of birth (stored as Gregorian)');
            $table->enum('gender', ['Male', 'Female'])->comment('Gender of the member');
            $table->string('christian_name', 100)->nullable()->comment('Christian/baptism name');
            
            // Address information
            $table->string('city', 100)->comment('City of residence');
            $table->string('sub_city', 100)->comment('Sub-city of residence');
            $table->string('woreda', 50)->comment('Woreda/district');
            $table->string('zone', 100)->nullable()->comment('Zone/keten');
            $table->string('block', 50)->nullable()->comment('Block number');
            $table->string('neighborhood', 200)->nullable()->comment('Neighborhood specific name');
            
            // Contact information
            $table->string('phone', 20)->unique()->comment('Personal phone number');
            $table->string('email', 191)->nullable()->comment('Email address');
            
            // Emergency contact
            $table->string('emergency_contact_name', 200)->comment('Emergency contact person name');
            $table->string('emergency_contact_phone', 20)->comment('Emergency contact phone number');
            
            // Spiritual information
            $table->string('confession_father_name', 200)->nullable()->comment('Confession father name');
            $table->string('confession_father_phone', 20)->nullable()->comment('Confession father phone');
            
            // Kids-specific fields
            $table->string('spiritual_education_level', 100)->nullable()->comment('Spiritual education level for kids');
            $table->text('special_talents')->nullable()->comment('Special talents and abilities');
            
            // Youth/Adult-specific fields (nullable for backward compatibility)
            $table->integer('family_size')->nullable()->comment('Total family size');
            $table->integer('brothers_count')->nullable()->comment('Number of brothers');
            $table->integer('sisters_count')->nullable()->comment('Number of sisters');
            $table->string('family_confession_father', 200)->nullable()->comment('Family confession father');
            $table->date('sunday_school_entry_year')->nullable()->comment('Year entered Sunday school');
            $table->text('past_service_departments')->nullable()->comment('Previous service departments');
            
            // Employment information
            $table->enum('occupation_status', ['Student', 'Employee'])->nullable()->comment('Current occupation status');
            $table->string('employment_status', 100)->nullable()->comment('Employment status details');
            $table->string('company_name', 200)->nullable()->comment('Current company name');
            $table->string('job_role', 200)->nullable()->comment('Current job role');
            $table->text('company_address')->nullable()->comment('Company address');
            
            // Marital information
            $table->enum('marital_status', ['Single', 'Married'])->nullable()->comment('Marital status');
            $table->date('marriage_year')->nullable()->comment('Year of marriage');
            $table->string('spouse_name', 200)->nullable()->comment('Spouse name');
            $table->string('spouse_phone', 20)->nullable()->comment('Spouse phone number');
            $table->integer('children_count')->nullable()->comment('Number of children');
            
            // Additional information
            $table->string('photo', 500)->nullable()->comment('Photo file path');
            $table->boolean('consent_for_photography')->default(false)->comment('Consent for photography');
            
            // Foreign keys
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->comment('Department that manages this member');
            
            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index(['member_type', 'status']);
            $table->index(['phone']);
            $table->index(['department_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
