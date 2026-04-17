-- Supabase Migration Script for Multi-Tenant Barbershop System
-- Execute this script in the Supabase SQL Editor

-- Ensure pgcrypto extension is available for UUID generation
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- NOTE: Supabase restricts ALTER DATABASE from SQL Editor for regular users.
-- Set the JWT secret in Supabase project settings instead of using ALTER DATABASE here.
-- Create tenants table
CREATE TABLE IF NOT EXISTS tenants (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create users table (extends Supabase auth.users)
CREATE TABLE IF NOT EXISTS users (
    id UUID REFERENCES auth.users(id) ON DELETE CASCADE PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    full_name VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create agendamentos table
CREATE TABLE IF NOT EXISTS agendamentos (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    profissional_id INTEGER NOT NULL,
    servico_id INTEGER NOT NULL,
    data_agendamento DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    status VARCHAR(50) DEFAULT 'agendado',
    nome_cliente VARCHAR(255) NOT NULL,
    telefone_cliente VARCHAR(20),
    observacao TEXT,
    valor_cobrado DECIMAL(10,2),
    forma_pagamento VARCHAR(50),
    infinitepay_transacao_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    data_conclusao TIMESTAMP WITH TIME ZONE
);

-- Create profissionais table
CREATE TABLE IF NOT EXISTS profissionais (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    especialidade VARCHAR(255),
    telefone VARCHAR(20),
    email VARCHAR(255),
    status VARCHAR(50) DEFAULT 'ativo',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create servicos table
CREATE TABLE IF NOT EXISTS servicos (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    duracao_minutos INTEGER NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'ativo',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create infinitepay_configuracao table
CREATE TABLE IF NOT EXISTS infinitepay_configuracao (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    client_id VARCHAR(255) NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    access_token_encrypted TEXT,
    refresh_token_encrypted TEXT,
    token_expires_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) DEFAULT 'ativo',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(tenant_id)
);

-- Create infinitepay_transacao table
CREATE TABLE IF NOT EXISTS infinitepay_transacao (
    id SERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    agendamento_id INTEGER REFERENCES agendamentos(id),
    infinitepay_charge_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    qr_code TEXT,
    payment_url TEXT,
    response_data JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_agendamentos_tenant_id ON agendamentos(tenant_id);
CREATE INDEX IF NOT EXISTS idx_agendamentos_profissional_data ON agendamentos(profissional_id, data_agendamento);
CREATE INDEX IF NOT EXISTS idx_agendamentos_status ON agendamentos(status);
CREATE INDEX IF NOT EXISTS idx_profissionais_tenant_id ON profissionais(tenant_id);
CREATE INDEX IF NOT EXISTS idx_servicos_tenant_id ON servicos(tenant_id);
CREATE INDEX IF NOT EXISTS idx_infinitepay_config_tenant_id ON infinitepay_configuracao(tenant_id);
CREATE INDEX IF NOT EXISTS idx_infinitepay_trans_tenant_id ON infinitepay_transacao(tenant_id);

-- Enable Row Level Security on all tables
ALTER TABLE tenants ENABLE ROW LEVEL SECURITY;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE agendamentos ENABLE ROW LEVEL SECURITY;
ALTER TABLE profissionais ENABLE ROW LEVEL SECURITY;
ALTER TABLE servicos ENABLE ROW LEVEL SECURITY;
ALTER TABLE infinitepay_configuracao ENABLE ROW LEVEL SECURITY;
ALTER TABLE infinitepay_transacao ENABLE ROW LEVEL SECURITY;

-- Create RLS policies for tenants table (admin only)
CREATE POLICY "Tenants are viewable by authenticated users" ON tenants
    FOR SELECT USING (auth.role() = 'authenticated');

CREATE POLICY "Tenants are insertable by service role" ON tenants
    FOR INSERT WITH CHECK (auth.role() = 'service_role');

CREATE POLICY "Tenants are updatable by service role" ON tenants
    FOR UPDATE USING (auth.role() = 'service_role');

-- Create RLS policies for users table
CREATE POLICY "Users can view their own tenant data" ON users
    FOR SELECT USING (auth.uid() = id);

CREATE POLICY "Users can insert their own data" ON users
    FOR INSERT WITH CHECK (auth.uid() = id);

CREATE POLICY "Users can update their own data" ON users
    FOR UPDATE USING (auth.uid() = id);

-- Create RLS policies for agendamentos table
CREATE POLICY "Users can view agendamentos from their tenant" ON agendamentos
    FOR SELECT USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can insert agendamentos for their tenant" ON agendamentos
    FOR INSERT WITH CHECK (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can update agendamentos from their tenant" ON agendamentos
    FOR UPDATE USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

-- Create RLS policies for profissionais table
CREATE POLICY "Users can view profissionais from their tenant" ON profissionais
    FOR SELECT USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can insert profissionais for their tenant" ON profissionais
    FOR INSERT WITH CHECK (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can update profissionais from their tenant" ON profissionais
    FOR UPDATE USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

-- Create RLS policies for servicos table
CREATE POLICY "Users can view servicos from their tenant" ON servicos
    FOR SELECT USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can insert servicos for their tenant" ON servicos
    FOR INSERT WITH CHECK (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can update servicos from their tenant" ON servicos
    FOR UPDATE USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

-- Create RLS policies for infinitepay_configuracao table
CREATE POLICY "Users can view infinitepay config from their tenant" ON infinitepay_configuracao
    FOR SELECT USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can insert infinitepay config for their tenant" ON infinitepay_configuracao
    FOR INSERT WITH CHECK (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can update infinitepay config from their tenant" ON infinitepay_configuracao
    FOR UPDATE USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

-- Create RLS policies for infinitepay_transacao table
CREATE POLICY "Users can view infinitepay transactions from their tenant" ON infinitepay_transacao
    FOR SELECT USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can insert infinitepay transactions for their tenant" ON infinitepay_transacao
    FOR INSERT WITH CHECK (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

CREATE POLICY "Users can update infinitepay transactions from their tenant" ON infinitepay_transacao
    FOR UPDATE USING (
        tenant_id IN (
            SELECT tenant_id FROM users WHERE id = auth.uid()
        )
    );

-- Create function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_tenants_updated_at BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_agendamentos_updated_at BEFORE UPDATE ON agendamentos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_profissionais_updated_at BEFORE UPDATE ON profissionais FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_servicos_updated_at BEFORE UPDATE ON servicos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_infinitepay_config_updated_at BEFORE UPDATE ON infinitepay_configuracao FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_infinitepay_trans_updated_at BEFORE UPDATE ON infinitepay_transacao FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert sample tenant (replace with your actual domain)
INSERT INTO tenants (name, domain, status) VALUES
('Barbearia Exemplo', 'barbearia-exemplo.localhost', 'active')
ON CONFLICT (domain) DO NOTHING;

-- Note: After running this script, you need to:
-- 1. Set up authentication in Supabase Dashboard
-- 2. Configure JWT secret in environment variables
-- 3. Update the .env file with your actual Supabase credentials