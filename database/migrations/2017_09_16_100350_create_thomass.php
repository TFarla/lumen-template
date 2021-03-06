<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateThomass extends Migration
{
    /**
     * Run the migrations.
     *
     * @return  void
     */
    public function up()
    {
        Schema::create('thomass', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->timestampTz('date_of_birth');
            $table->integer('age');

            $table->timestampsTz();


            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return  void
     */
    public function down()
    {
        Schema::drop('thomass');
    }
}