-- InfinitePay Integration Migration
-- Execute this script in app/database/barbearia.db to add InfinitePay support

-- Create table for InfinitePay configuration
CREATE TABLE IF NOT EXISTS infinitepay_configuracao (
    id INTEGER PRIMARY KEY,
    client_id TEXT NOT NULL,
    client_secret_enc TEXT NOT NULL,  -- encrypted with AES-256-CBC
    access_token_enc TEXT,
    refresh_token_enc TEXT,
    token_expires_at TEXT,
    ativo CHAR(1) NOT NULL DEFAULT 'Y',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT
);

-- Create table for InfinitePay transactions linked to appointments
CREATE TABLE IF NOT EXISTS infinitepay_transacao (
    id INTEGER PRIMARY KEY,
    agendamento_id INTEGER NOT NULL,
    charge_id TEXT NOT NULL,          -- ID returned by InfinitePay API
    valor NUMERIC(10,2) NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending', -- pending|approved|refused|cancelled
    webhook_payload TEXT,             -- JSON raw from webhook
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT,
    FOREIGN KEY (agendamento_id) REFERENCES agendamento (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
);

-- Add infinitepay_transacao_id column to agendamento table
-- Note: Use this with caution if the column already exists
-- ALTER TABLE agendamento ADD COLUMN infinitepay_transacao_id INTEGER;

-- Create index for better query performance
CREATE INDEX IF NOT EXISTS infinitepay_transacao_agendamento_idx ON infinitepay_transacao (agendamento_id);
CREATE INDEX IF NOT EXISTS infinitepay_transacao_charge_id_idx ON infinitepay_transacao (charge_id);
