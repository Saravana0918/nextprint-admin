<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamFieldsToTeamsTable extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            // players likely json already; if not, add it
            if (!Schema::hasColumn('teams', 'players')) {
                $table->json('players')->nullable();
            }

            // add fields your controller tries to save
            if (!Schema::hasColumn('teams', 'team_logo_url')) {
                $table->string('team_logo_url')->nullable()->after('players');
            }
            if (!Schema::hasColumn('teams', 'preview_url')) {
                $table->text('preview_url')->nullable()->after('team_logo_url');
            }
            if (!Schema::hasColumn('teams', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('preview_url');
            }

            // optional: timestamps if missing
            if (!Schema::hasColumn('teams', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'team_logo_url')) $table->dropColumn('team_logo_url');
            if (Schema::hasColumn('teams', 'preview_url')) $table->dropColumn('preview_url');
            if (Schema::hasColumn('teams', 'created_by')) $table->dropColumn('created_by');
            // DON'T drop players or timestamps here unless you're sure
        });
    }
}
