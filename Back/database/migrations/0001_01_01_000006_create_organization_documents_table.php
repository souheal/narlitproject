<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('organization_profile_id')->constrained('organization_profiles')->cascadeOnDelete();
            $table->enum('type', ['certificate', 'tax', 'contract', 'other']);
            $table->string('file_path', 2048);
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('uploaded_at')->useCurrent();
            $table->timestampsTz();

            $table->index('organization_profile_id');
            $table->index('type');
            $table->index(['organization_profile_id', 'type']);
            $table->index('uploaded_by');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE organization_documents ADD CONSTRAINT organization_documents_file_size_check CHECK (file_size IS NULL OR file_size >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
    }
};
