<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute la génération IA intégrale à l'offre Pro+ existante.
     */
    public function up(): void
    {
        $plan = DB::table('plans')->where('code', 'proplus')->first();

        if ($plan === null) {
            return;
        }

        $features = json_decode($plan->features, true) ?: [];

        if (! in_array('ai_full', $features, true)) {
            $features[] = 'ai_full';
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $plan = DB::table('plans')->where('code', 'proplus')->first();

        if ($plan !== null) {
            $features = array_values(array_diff(json_decode($plan->features, true) ?: [], ['ai_full']));
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }
};
