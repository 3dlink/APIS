<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFileTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('files', function (Blueprint $table) {
			$table->increments('id');
			$table->string('name');
			$table->string('originalName')->nullable();
			$table->string('extension')->nullable();
			$table->string('route')->nullable();
			$table->integer('size')->nullable();
			$table->integer('parent_id')->default(0);
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
		Schema::drop('files');
	}
}