<?php
// database/migrations/xxxx_xx_xx_create_outlook_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('outlook_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique(); // Outlook email address
            $table->string('name')->nullable(); // Display name
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('outlook_accounts');
    }
};
