<?php
return [
    'supabase' => [
        'url' => getenv('SUPABASE_URL') ?: 'https://ilsvlupikbqfpblaeyqc.supabase.co',
        'anon_key' => getenv('SUPABASE_ANON_KEY') ?: '',
        'service_key' => getenv('SUPABASE_SERVICE_KEY') ?: '',
        'jwt_secret' => getenv('SUPABASE_JWT_SECRET') ?: '',
    ],
    'multi_tenant' => [
        'enabled' => true,
        'domain_header' => 'HTTP_HOST', // or 'X-Tenant-Domain'
        'tenant_cache_ttl' => 3600, // 1 hour
    ],
];
