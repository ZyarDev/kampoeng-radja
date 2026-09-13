<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('kpi_ops_items',function(Blueprint $t){$t->decimal('target_bulanan',12,4)->default(0);$t->unsignedTinyInteger('bulan')->nullable();});} public function down():void{Schema::table('kpi_ops_items',function(Blueprint $t){$t->dropColumn(['target_bulanan','bulan']);});} };
