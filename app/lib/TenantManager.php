<?php
/**
 * TenantManager
 *
 * Manages multi-tenant context and tenant isolation
 * Handles tenant resolution, caching, and context switching
 *
 * @version    1.0
 * @package    lib
 */
class TenantManager
{
    private static $instance = null;
    private $config;
    private $currentTenant = null;
    private $cache = [];

    private function __construct()
    {
        $this->config = include 'app/config/supabase.php';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resolve tenant from request context
     * @return array|null Tenant data or null if not found
     */
    public function resolveTenant()
    {
        $domain = $this->getDomainFromRequest();

        // Check cache first
        if (isset($this->cache[$domain])) {
            return $this->cache[$domain];
        }

        // Query tenant from database
        $supabase = SupabaseClient::getInstance();
        $tenants = $supabase->select('tenants', '*', ['domain' => $domain]);

        if (empty($tenants)) {
            return null;
        }

        $tenant = $tenants[0];
        $this->cache[$domain] = $tenant;

        return $tenant;
    }

    /**
     * Set current tenant context
     * @param array $tenant
     */
    public function setCurrentTenant($tenant)
    {
        $this->currentTenant = $tenant;

        // Set tenant context in SupabaseClient
        $supabase = SupabaseClient::getInstance();
        $supabase->setTenantId($tenant['id']);
    }

    /**
     * Get current tenant
     * @return array|null
     */
    public function getCurrentTenant()
    {
        return $this->currentTenant;
    }

    /**
     * Get current tenant ID
     * @return string|null
     */
    public function getCurrentTenantId()
    {
        return $this->currentTenant ? $this->currentTenant['id'] : null;
    }

    /**
     * Create new tenant
     * @param array $data
     * @return array
     */
    public function createTenant($data)
    {
        $supabase = SupabaseClient::getInstance();

        // Validate required fields
        if (!isset($data['name']) || !isset($data['domain'])) {
            throw new Exception('Tenant name and domain are required');
        }

        // Check if domain already exists
        $existing = $supabase->select('tenants', 'id', ['domain' => $data['domain']]);
        if (!empty($existing)) {
            throw new Exception('Domain already exists');
        }

        $tenantData = [
            'name' => $data['name'],
            'domain' => $data['domain'],
            'status' => $data['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $supabase->insert('tenants', $tenantData);
    }

    /**
     * Update tenant
     * @param string $tenantId
     * @param array $data
     * @return array
     */
    public function updateTenant($tenantId, $data)
    {
        $supabase = SupabaseClient::getInstance();

        $data['updated_at'] = date('Y-m-d H:i:s');

        return $supabase->update('tenants', $data, ['id' => $tenantId]);
    }

    /**
     * Delete tenant (soft delete)
     * @param string $tenantId
     * @return array
     */
    public function deleteTenant($tenantId)
    {
        $supabase = SupabaseClient::getInstance();

        return $supabase->update('tenants', [
            'status' => 'deleted',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $tenantId]);
    }

    /**
     * Get all tenants (admin only)
     * @param array $filters
     * @return array
     */
    public function getTenants($filters = [])
    {
        $supabase = SupabaseClient::getInstance();

        // Temporarily remove tenant context for admin operations
        $currentTenantId = $supabase->getTenantId();
        $supabase->setTenantId(null);

        try {
            $tenants = $supabase->select('tenants', '*', $filters);
            return $tenants;
        } finally {
            // Restore tenant context
            $supabase->setTenantId($currentTenantId);
        }
    }

    /**
     * Initialize tenant context for request
     * Call this at the beginning of each request
     */
    public function initializeTenantContext()
    {
        $tenant = $this->resolveTenant();

        if (!$tenant) {
            throw new Exception('Tenant not found for domain: ' . $this->getDomainFromRequest());
        }

        $this->setCurrentTenant($tenant);
    }

    /**
     * Get domain from current request
     * @return string
     */
    private function getDomainFromRequest()
    {
        $header = $this->config['multi_tenant']['domain_header'];

        if ($header === 'HTTP_HOST') {
            return $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        return $_SERVER[$header] ?? 'localhost';
    }

    /**
     * Clear tenant cache
     */
    public function clearCache()
    {
        $this->cache = [];
    }

    /**
     * Check if multi-tenant is enabled
     * @return bool
     */
    public function isMultiTenantEnabled()
    {
        return $this->config['multi_tenant']['enabled'] ?? false;
    }
}
