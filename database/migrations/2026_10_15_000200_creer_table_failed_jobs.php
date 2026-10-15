<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jobs en échec (files Redis) : supervision de la file d'attente (chapitre 6.8, ENF-EXP-02).
 * Table technique de Laravel, rangée dans le schéma app.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE app.failed_jobs (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid text UNIQUE NOT NULL,
  connection text NOT NULL,
  queue text NOT NULL,
  payload text NOT NULL,
  exception text NOT NULL,
  failed_at timestamptz NOT NULL DEFAULT now()
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS app.failed_jobs;');
    }
};
