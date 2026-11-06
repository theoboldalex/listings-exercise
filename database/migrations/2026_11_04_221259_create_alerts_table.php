<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // The alert is about the listing; the search is only the reason it
            // matched, so deleting a search keeps the user's alert history.
            $table->foreignId('saved_search_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // One alert per user per listing, however many of their searches
            // match. Also makes alert generation safe to retry.
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
