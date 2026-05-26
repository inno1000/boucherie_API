<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn(['produit_id', 'quantite']);
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->foreignUuid('produit_id')->nullable()->constrained('produits')->cascadeOnDelete();
            $table->decimal('quantite', 10, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn(['produit_id', 'quantite']);
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->decimal('quantite', 10, 3);
        });
    }
};
