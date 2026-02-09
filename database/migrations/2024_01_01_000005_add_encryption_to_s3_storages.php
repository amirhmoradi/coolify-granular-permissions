<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s3_storages', function (Blueprint $table) {
            $table->boolean('encryption_enabled')->default(false)->after('is_usable');
            $table->longText('encryption_password')->nullable()->after('encryption_enabled');
            $table->longText('encryption_salt')->nullable()->after('encryption_password');
            $table->string('filename_encryption', 20)->default('off')->after('encryption_salt');
            $table->boolean('directory_name_encryption')->default(false)->after('filename_encryption');
        });
    }

    public function down(): void
    {
        Schema::table('s3_storages', function (Blueprint $table) {
            $table->dropColumn([
                'encryption_enabled',
                'encryption_password',
                'encryption_salt',
                'filename_encryption',
                'directory_name_encryption',
            ]);
        });
    }
};
