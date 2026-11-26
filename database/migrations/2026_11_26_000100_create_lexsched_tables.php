<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Practice areas / services the office offers (Family Law, Notarial Services, ...).
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('is_notarial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A lawyer's professional profile, linked to a user account with role "lawyer".
        Schema::create('lawyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('roll_number', 30)->nullable();
            $table->string('ibp_chapter')->nullable();
            $table->unsignedSmallInteger('years_of_practice')->nullable();
            $table->string('notarial_commission_no', 50)->nullable();
            $table->date('commission_expires_on')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('accepts_bookings')->default(true);
            $table->timestamps();
        });

        Schema::create('lawyer_service', function (Blueprint $table) {
            $table->foreignId('lawyer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['lawyer_id', 'service_id']);
        });

        // Notarial document types, each with its own fee and requirements.
        Schema::create('notarial_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('fee', 10, 2);
            $table->json('requirements');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_phone', 20)->nullable();
            $table->string('client_email')->nullable();
            $table->foreignId('lawyer_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('scheduled_date');
            $table->string('start_time', 5); // "HH:MM", 24-hour
            $table->string('status', 20)->default('pending')->index();
            $table->string('mode', 20)->default('in_office');
            $table->string('source', 20)->default('online');
            $table->text('concern')->nullable();
            $table->string('opposing_party')->nullable();
            $table->decimal('fee', 10, 2);
            $table->string('payment_method', 20)->default('office');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 40)->nullable();
            $table->string('official_receipt_no', 30)->nullable();

            // Notarial details (only for notarial services).
            $table->foreignId('notarial_document_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('copies')->default(1);
            $table->boolean('check_appeared')->default(false);
            $table->boolean('check_id_presented')->default(false);
            $table->boolean('check_document_complete')->default(false);
            $table->string('id_type', 50)->nullable();

            $table->text('lawyer_notes')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamp('reminded_at')->nullable();

            // Holds "lawyerId|date|time" while the appointment occupies its slot, NULL once it is
            // declined or cancelled. The unique index makes double booking impossible at database level.
            $table->string('slot_key', 40)->nullable()->unique();

            $table->timestamps();
            $table->index(['lawyer_id', 'scheduled_date']);
        });

        Schema::create('appointment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        // Hours a lawyer is unavailable (court hearings, leave). NULL start_time = the whole day.
        Schema::create('blocked_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time', 5)->nullable();
            $table->string('reason', 100);
            $table->timestamps();
            $table->index(['lawyer_id', 'date']);
        });

        // The notarial register: one row per notarized document.
        Schema::create('notarial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lawyer_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('series');
            $table->unsignedInteger('doc_no');
            $table->unsignedInteger('page_no');
            $table->string('book_no', 10);
            $table->date('notarized_on');
            $table->string('document_name');
            $table->string('principal_name');
            $table->string('id_type', 50);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->decimal('fee', 10, 2);
            $table->timestamps();
            $table->unique(['lawyer_id', 'series', 'doc_no']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notarial_entries');
        Schema::dropIfExists('blocked_slots');
        Schema::dropIfExists('appointment_attachments');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('notarial_documents');
        Schema::dropIfExists('lawyer_service');
        Schema::dropIfExists('lawyers');
        Schema::dropIfExists('services');
    }
};
