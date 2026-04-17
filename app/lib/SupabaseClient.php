<?php
/**
 * SupabaseClient
 *
 * PostgreSQL client for Supabase with multi-tenant support
 * Implements Row-Level Security and tenant isolation
 *
 * @version    1.0
 * @package    lib
 */
class SupabaseClient
{
    private static $instance = null;
    private $config;
    private $tenantId = null;

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
     * Set current tenant context
     * @param string $tenantId
     */
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Get current tenant ID
     * @return string|null
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     * Execute SELECT query with tenant isolation
     * @param string $table
     * @param string $select
     * @param array $where
     * @param array $options
     * @return array
     */
    public function select($table, $select = '*', $where = [], $options = [])
    {
        $url = $this->buildUrl($table, $select, $where, $options);
        return $this->request('GET', $url, null, $this->getApiKey());
    }

    /**
     * Execute INSERT query
     * @param string $table
     * @param array $data
     * @return array
     */
    public function insert($table, $data)
    {
        $url = $this->config['supabase']['url'] . '/rest/v1/' . $table;

        // Add tenant_id if not present and tenant is set
        if ($this->tenantId && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $this->tenantId;
        }

        return $this->request('POST', $url, json_encode($data), $this->getServiceKey());
    }

    /**
     * Execute UPDATE query with tenant isolation
     * @param string $table
     * @param array $data
     * @param array $where
     * @return array
     */
    public function update($table, $data, $where = [])
    {
        $url = $this->buildUrl($table, '*', $where);

        // Add tenant_id to where if not present and tenant is set
        if ($this->tenantId && !isset($where['tenant_id'])) {
            $url .= '&tenant_id=eq.' . $this->tenantId;
        }

        return $this->request('PATCH', $url, json_encode($data), $this->getServiceKey());
    }

    /**
     * Execute DELETE query with tenant isolation
     * @param string $table
     * @param array $where
     * @return array
     */
    public function delete($table, $where = [])
    {
        $url = $this->buildUrl($table, '*', $where);

        // Add tenant_id to where if not present and tenant is set
        if ($this->tenantId && !isset($where['tenant_id'])) {
            $url .= '&tenant_id=eq.' . $this->tenantId;
        }

        return $this->request('DELETE', $url, null, $this->getServiceKey());
    }

    /**
     * Execute raw SQL query (use with caution)
     * @param string $sql
     * @return array
     */
    public function query($sql)
    {
        $url = $this->config['supabase']['url'] . '/rest/v1/rpc/execute_sql';
        $data = ['sql' => $sql];

        return $this->request('POST', $url, json_encode($data), $this->getServiceKey());
    }

    /**
     * Build REST API URL with query parameters
     * @param string $table
     * @param string $select
     * @param array $where
     * @param array $options
     * @return string
     */
    private function buildUrl($table, $select = '*', $where = [], $options = [])
    {
        $url = $this->config['supabase']['url'] . '/rest/v1/' . $table;
        $params = ['select=' . urlencode($select)];

        // Add where conditions
        foreach ($where as $key => $value) {
            $params[] = $key . '=eq.' . urlencode($value);
        }

        // Add tenant isolation if set
        if ($this->tenantId) {
            $params[] = 'tenant_id=eq.' . $this->tenantId;
        }

        // Add additional options
        if (isset($options['limit'])) {
            $params[] = 'limit=' . (int)$options['limit'];
        }
        if (isset($options['offset'])) {
            $params[] = 'offset=' . (int)$options['offset'];
        }
        if (isset($options['order'])) {
            $params[] = 'order=' . urlencode($options['order']);
        }

        return $url . '?' . implode('&', $params);
    }

    /**
     * Make HTTP request to Supabase
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @param string $apiKey
     * @return array
     * @throws Exception
     */
    private function request($method, $url, $body = null, $apiKey = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $headers = [
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        // Add tenant context to headers if set
        if ($this->tenantId) {
            $headers[] = 'X-Tenant-ID: ' . $this->tenantId;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }

        if ($statusCode >= 400) {
            throw new Exception('Supabase API Error [' . $statusCode . ']: ' . $response);
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Get anon key for public operations
     * @return string
     */
    private function getApiKey()
    {
        return $this->config['supabase']['anon_key'];
    }

    /**
     * Get service key for admin operations
     * @return string
     */
    private function getServiceKey()
    {
        return $this->config['supabase']['service_key'];
    }

    /**
     * Get JWT secret for token operations
     * @return string
     */
    public function getJwtSecret()
    {
        return $this->config['supabase']['jwt_secret'];
    }
}
