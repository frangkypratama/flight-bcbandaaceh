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
        Schema::table('flights', function (Blueprint $table) {
            $table->string('doc_type')->nullable()->after('no_show');
            $table->string('filename')->nullable()->after('doc_type');
            $table->string('gmail_message_id')->nullable()->after('filename')->index();
            $table->string('sender_email')->nullable()->after('gmail_message_id');
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->foreignId('flight_id')->nullable()->after('id')->constrained('flights')->nullOnDelete();
            $table->string('status')->default('boarded')->after('bag_kg');
            $table->string('gender')->nullable()->after('status');
            $table->string('pax_type')->default('ADT')->after('gender');
            $table->json('bag_tags')->nullable()->after('pax_type');
            $table->string('ticket_no')->nullable()->after('bag_tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['flight_id']);
            $table->dropColumn(['flight_id', 'status', 'gender', 'pax_type', 'bag_tags', 'ticket_no']);
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['doc_type', 'filename', 'gmail_message_id', 'sender_email']);
        });
    }
};
