<?php
/**
 * SupabaseRecord
 *
 * Base class for all models using Supabase with multi-tenant support
 * Replaces TRecord with Supabase operations
 *
 * @version    1.0
 * @package    lib
 */
abstract class SupabaseRecord
{
    protected $data = [];
    protected $originalData = [];
    protected $supabase;
    protected $tableName;
    protected $primaryKey = 'id';
    protected $tenantField = 'tenant_id';

    public function __construct($id = null)
    {
        $this->supabase = SupabaseClient::getInstance();

        if ($id !== null) {
            $this->load($id);
        }
    }

    /**
     * Load record by ID
     * @param mixed $id
     * @return bool
     */
    public function load($id)
    {
        $records = $this->supabase->select($this->tableName, '*', [$this->primaryKey => $id]);

        if (!empty($records)) {
            $this->data = $records[0];
            $this->originalData = $records[0];
            return true;
        }

        return false;
    }

    /**
     * Save record (insert or update)
     * @return bool
     */
    public function save()
    {
        if (isset($this->data[$this->primaryKey]) && !empty($this->data[$this->primaryKey])) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new record
     * @return bool
     */
    protected function insert()
    {
        // Add tenant_id if not set and multi-tenant is enabled
        $tenantManager = TenantManager::getInstance();
        if ($tenantManager->isMultiTenantEnabled() && !isset($this->data[$this->tenantField])) {
            $this->data[$this->tenantField] = $tenantManager->getCurrentTenantId();
        }

        // Add timestamps
        if (!isset($this->data['created_at'])) {
            $this->data['created_at'] = date('Y-m-d H:i:s');
        }
        $this->data['updated_at'] = date('Y-m-d H:i:s');

        $result = $this->supabase->insert($this->tableName, $this->data);

        if (!empty($result)) {
            $this->data[$this->primaryKey] = $result[0][$this->primaryKey];
            $this->originalData = $this->data;
            return true;
        }

        return false;
    }

    /**
     * Update existing record
     * @return bool
     */
    protected function update()
    {
        $this->data['updated_at'] = date('Y-m-d H:i:s');

        $result = $this->supabase->update(
            $this->tableName,
            $this->data,
            [$this->primaryKey => $this->data[$this->primaryKey]]
        );

        if (!empty($result)) {
            $this->originalData = $this->data;
            return true;
        }

        return false;
    }

    /**
     * Delete record
     * @return bool
     */
    public function delete()
    {
        if (!isset($this->data[$this->primaryKey])) {
            return false;
        }

        $result = $this->supabase->delete(
            $this->tableName,
            [$this->primaryKey => $this->data[$this->primaryKey]]
        );

        return !empty($result);
    }

    /**
     * Get all records with optional filters
     * @param array $filters
     * @param array $options
     * @return array
     */
    public static function findAll($filters = [], $options = [])
    {
        $instance = new static();
        return $instance->supabase->select($instance->tableName, '*', $filters, $options);
    }

    /**
     * Find record by ID
     * @param mixed $id
     * @return static|null
     */
    public static function find($id)
    {
        $instance = new static();
        if ($instance->load($id)) {
            return $instance;
        }
        return null;
    }

    /**
     * Find records by criteria
     * @param array $criteria
     * @param array $options
     * @return array
     */
    public static function findBy($criteria, $options = [])
    {
        $instance = new static();
        return $instance->supabase->select($instance->tableName, '*', $criteria, $options);
    }

    /**
     * Count records
     * @param array $filters
     * @return int
     */
    public static function count($filters = [])
    {
        $instance = new static();
        $records = $instance->supabase->select($instance->tableName, 'count', $filters);
        return $records[0]['count'] ?? 0;
    }

    /**
     * Magic getter
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->data[$property] ?? null;
    }

    /**
     * Magic setter
     * @param string $property
     * @param mixed $value
     */
    public function __set($property, $value)
    {
        $this->data[$property] = $value;
    }

    /**
     * Check if property is set
     * @param string $property
     * @return bool
     */
    public function __isset($property)
    {
        return isset($this->data[$property]);
    }

    /**
     * Get all data as array
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Load data from array
     * @param array $data
     */
    public function fromArray($data)
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Check if record has changes
     * @return bool
     */
    public function isDirty()
    {
        return $this->data !== $this->originalData;
    }

    /**
     * Get changed fields
     * @return array
     */
    public function getDirtyFields()
    {
        $dirty = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->originalData[$key]) || $this->originalData[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }
}
