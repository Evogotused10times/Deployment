<?php

// database/migrations/2025_01_01_000003_create_applications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            // Applicant info (person filling the form)
            $table->string('applicant_name');
            $table->string('applicant_email')->index();
            $table->string('applicant_phone')->nullable();
            $table->text('applicant_address')->nullable();
            $table->string('facebook_messenger')->nullable();
            $table->string('next_of_kin')->nullable();
            $table->string('next_of_kin_contact')->nullable();

            // Deceased info
            $table->string('deceased_name');
            $table->date('deceased_dod')->nullable();
            $table->string('deceased_age')->nullable();

            // service preferences
            $table->enum('service_type', ['Lawn Lot','Garden Lot','Mausoleum','Community Vaults'])->default('Lawn Lot');
            $table->json('preferred_plots')->nullable(); // array of requested plots (lot_number, block, desc, price)
            $table->text('terms')->nullable();
            $table->text('remarks')->nullable();

            // workflow
            $table->enum('status', ['pending','approved','denied'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('assigned_plot_id')->nullable()->constrained('plots')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('applications');
    }
};
