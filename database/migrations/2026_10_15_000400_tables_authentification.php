<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tables d'authentification absentes du § 5.2 (décisions validées au plan du lot L1) :
 * - app.lien_connexion : lien de connexion (15 min, usage unique, EF-M0-03, ENF-SEC-02) et lien d'activation (§ 2.4) ;
 *   seule l'empreinte SHA-256 du jeton est conservée ;
 * - app.code_verification : code de double authentification envoyé par e-mail (EF-M0-03, ENF-SEC-03) ;
 * - app.password_reset_tokens : réinitialisation du mot de passe (Fortify).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE app.lien_connexion (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint NOT NULL REFERENCES app."user"(id) ON DELETE CASCADE,
  type text NOT NULL CHECK (type IN ('connexion','activation')),
  jeton_hash text UNIQUE NOT NULL,                    -- SHA-256 du jeton ; le jeton lui-même n'est jamais stocké
  expire_le timestamptz NOT NULL,
  utilise_le timestamptz,
  ip_demande inet,
  cree_par bigint REFERENCES app."user"(id),          -- auteur de l'invitation (activation)
  cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app.lien_connexion (user_id, type);

CREATE TABLE app.code_verification (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint NOT NULL REFERENCES app."user"(id) ON DELETE CASCADE,
  code_hash text NOT NULL,                            -- HMAC-SHA-256 du code
  expire_le timestamptz NOT NULL,
  tentatives smallint NOT NULL DEFAULT 0,
  utilise_le timestamptz,
  cree_le timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app.code_verification (user_id, cree_le);

CREATE TABLE app.password_reset_tokens (
  email citext PRIMARY KEY,
  token text NOT NULL,
  created_at timestamptz
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS app.password_reset_tokens, app.code_verification, app.lien_connexion;');
    }
};
