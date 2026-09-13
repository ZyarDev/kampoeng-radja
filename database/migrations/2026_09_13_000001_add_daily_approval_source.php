<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('kpi_daily_reports',function(Blueprint $t){$t->string('approval_source',20)->nullable()->after('approved_at');});}public function down():void{Schema::table('kpi_daily_reports',function(Blueprint $t){$t->dropColumn('approval_source');});}};
