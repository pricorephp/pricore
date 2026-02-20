<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 50);
            $table->string('token', 64)->unique();
            $table->foreignUuid('invited_by')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_uuid', 'email'], 'org_invitations_org_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
