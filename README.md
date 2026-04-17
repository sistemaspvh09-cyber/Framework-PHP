# Sistema de Barbearia Multi-Tenant com Supabase

Este é um sistema de gerenciamento de barbearia desenvolvido com Adianti Framework PHP, migrado para Supabase PostgreSQL com arquitetura multi-tenant para revenda.

## 🚀 Funcionalidades

- **Multi-Tenant**: Isolamento completo de dados por tenant
- **Agendamentos**: Sistema completo de agendamento de serviços
- **Pagamentos**: Integração com InfinitePay Tap to Pay
- **Profissionais**: Gestão de profissionais e especialidades
- **Serviços**: Cadastro de serviços e preços
- **Relatórios**: Movimentação de caixa e relatórios

## 📋 Pré-requisitos

- PHP 8.2+
- Composer
- Conta Supabase
- Credenciais InfinitePay (opcional)

## 🛠️ Instalação e Configuração

### 1. Configurar Supabase

1. Crie um novo projeto no [Supabase](https://supabase.com)
2. Execute o script SQL `supabase_migration.sql` no SQL Editor do Supabase
3. Configure as variáveis de ambiente no arquivo `.env`:

```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-key
SUPABASE_JWT_SECRET=your-jwt-secret
```

### 2. Configurar Tenant

Para cada cliente/revenda, crie um tenant:

```php
$tenantManager = TenantManager::getInstance();
$tenantManager->createTenant([
    'name' => 'Barbearia do João',
    'domain' => 'barbearia-joao.com.br'
]);
```

### 3. Configurar Autenticação

1. No Supabase Dashboard, vá para Authentication > Settings
2. Configure o Site URL e Redirect URLs
3. Ative a autenticação por email/senha

### 4. Executar o Sistema

```bash
# Instalar dependências
composer install

# Executar servidor local
php -S localhost:8000
```

Acesse: `http://localhost:8000`

## 🔧 Arquitetura Multi-Tenant

### Isolamento de Dados

- **Row-Level Security (RLS)**: Políticas automáticas por tenant
- **Tenant Context**: Isolamento automático via `tenant_id`
- **Domain Resolution**: Resolução automática de tenant por domínio

### Estrutura de Banco

```
tenants (dados dos tenants)
├── users (usuários por tenant)
├── agendamentos (agendamentos por tenant)
├── profissionais (profissionais por tenant)
├── servicos (serviços por tenant)
├── infinitepay_configuracao (config InfinitePay por tenant)
└── infinitepay_transacao (transações InfinitePay por tenant)
```

## 💳 Configuração InfinitePay

Para cada tenant, configure as credenciais do InfinitePay:

1. Acesse a configuração no menu do sistema
2. Insira Client ID e Client Secret
3. Teste a conexão
4. Configure webhooks para: `https://your-domain.com/webhook.php`

## 🌐 Deploy Multi-Tenant

### Estratégia de Domínios

- **Subdomínios**: `cliente1.seudominio.com`
- **Domínios próprios**: `barbearia-cliente.com`
- **Configuração**: Atualize o campo `domain` na tabela `tenants`

### Exemplo de Configuração Nginx

```nginx
server {
    listen 80;
    server_name ~^(?<tenant>.+)\.seudominio\.com$;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Tenant-Domain $tenant.seudominio.com;
    }
}
```

## 🔒 Segurança

- **Row-Level Security**: Isolamento automático de dados
- **JWT Authentication**: Autenticação baseada em tokens
- **Encryption**: Credenciais sensíveis criptografadas
- **HMAC Validation**: Validação de webhooks InfinitePay

## 📊 Monitoramento

### Logs Importantes

- Tentativas de acesso a tenants inexistentes
- Falhas de autenticação InfinitePay
- Erros de webhook
- Operações de caixa

### Métricas Recomendadas

- Número de tenants ativos
- Volume de agendamentos por tenant
- Taxa de conversão de pagamentos
- Tempo médio de resposta

## 🚀 Escalabilidade

### Otimizações

- **Indexes**: Índices otimizados para consultas frequentes
- **Connection Pooling**: Pool de conexões Supabase
- **Caching**: Cache de configuração de tenants
- **CDN**: Assets estáticos via CDN

### Limites Supabase

- 500MB de banco gratuito
- 50.000 requisições/mês gratuito
- Upgrade automático para tiers pagos

## 🐛 Troubleshooting

### Problemas Comuns

1. **Tenant não encontrado**
   - Verifique se o domínio está cadastrado na tabela `tenants`
   - Confirme o header `HTTP_HOST`

2. **Erro de autenticação InfinitePay**
   - Verifique credenciais no tenant específico
   - Teste conexão via interface

3. **Webhook não funciona**
   - Confirme URL do webhook no InfinitePay
   - Verifique logs de erro no servidor

### Debug Mode

Ative logs detalhados em `app/config/application.php`:

```php
'general' => [
    'debug' => true,
    // ...
]
```

## 📝 API Reference

### TenantManager

```php
// Criar tenant
$tenantManager->createTenant(['name' => '...', 'domain' => '...']);

// Definir contexto
$tenantManager->setCurrentTenant($tenant);

// Resolver tenant por domínio
$tenant = $tenantManager->resolveTenant();
```

### SupabaseClient

```php
// Buscar dados
$data = $supabase->select('tabela', '*', ['campo' => 'valor']);

// Inserir
$id = $supabase->insert('tabela', ['campo' => 'valor']);

// Atualizar
$supabase->update('tabela', ['campo' => 'novo'], ['id' => 1]);
```

## 🤝 Suporte

Para suporte técnico ou dúvidas sobre implementação:

1. Verifique os logs em `tmp/`
2. Consulte documentação do Adianti Framework
3. Revise configurações do Supabase
4. Teste com dados de exemplo

## 📄 Licença

Este projeto está licenciado sob MIT License.