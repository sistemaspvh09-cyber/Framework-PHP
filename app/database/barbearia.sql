PRAGMA foreign_keys = ON;

-- Tabelas principais
CREATE TABLE profissional (
    id INTEGER PRIMARY KEY,
    nome TEXT NOT NULL,
    ativo CHAR(1) NOT NULL DEFAULT 'Y',
    telefone TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT
);

CREATE TABLE servico (
    id INTEGER PRIMARY KEY,
    nome TEXT NOT NULL,
    preco NUMERIC(10,2) NOT NULL DEFAULT 0,
    duracao_minutos INTEGER NOT NULL DEFAULT 0,
    ativo CHAR(1) NOT NULL DEFAULT 'Y',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT
);

CREATE TABLE profissional_servico (
    id INTEGER PRIMARY KEY,
    profissional_id INTEGER NOT NULL,
    servico_id INTEGER NOT NULL,
    UNIQUE (profissional_id, servico_id),
    FOREIGN KEY (profissional_id) REFERENCES profissional (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    FOREIGN KEY (servico_id) REFERENCES servico (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

CREATE TABLE agenda_profissional (
    id INTEGER PRIMARY KEY,
    profissional_id INTEGER NOT NULL,
    dia_semana INTEGER NOT NULL,
    hora_inicio TEXT NOT NULL,
    hora_fim TEXT NOT NULL,
    intervalo_minutos INTEGER,
    ativo CHAR(1) NOT NULL DEFAULT 'Y',
    FOREIGN KEY (profissional_id) REFERENCES profissional (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

CREATE TABLE agendamento (
    id INTEGER PRIMARY KEY,
    profissional_id INTEGER NOT NULL,
    servico_id INTEGER NOT NULL,
    data_agendamento TEXT NOT NULL,
    hora_inicio TEXT NOT NULL,
    hora_fim TEXT NOT NULL,
    status TEXT NOT NULL,
    nome_cliente TEXT NOT NULL,
    telefone_cliente TEXT,
    observacao TEXT,
    valor_cobrado NUMERIC(10,2),
    forma_pagamento TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT,
    data_conclusao TEXT,
    FOREIGN KEY (profissional_id) REFERENCES profissional (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    FOREIGN KEY (servico_id) REFERENCES servico (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

CREATE TABLE movimento_caixa (
    id INTEGER PRIMARY KEY,
    data_movimento TEXT NOT NULL,
    tipo CHAR(1) NOT NULL,
    descricao TEXT NOT NULL,
    valor NUMERIC(10,2) NOT NULL,
    categoria TEXT,
    agendamento_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (agendamento_id) REFERENCES agendamento (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

-- Indices
CREATE INDEX agenda_prof_dia_idx ON agenda_profissional (profissional_id, dia_semana);
CREATE INDEX profissional_servico_idx ON profissional_servico (profissional_id, servico_id);
CREATE INDEX agendamento_prof_data_idx ON agendamento (profissional_id, data_agendamento);
