<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->boolean('approved')->default(true);
            $table->datetime('approved_at')->nullable();
            $table->uuid('reciever_identifier');
            $table->uuid('sender_identifier')->nullable()->default(null);
            $table->unsignedInteger('state_id');
            $table->boolean('is_recharge')->default(false);
            $table->uuid('transaction_identifier');
            $table->double('amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
