<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_ssh_keys', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->text('public_key');
            $table->text('private_key');
            $table->string('fingerprint');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_ssh_keys');
    }
};
