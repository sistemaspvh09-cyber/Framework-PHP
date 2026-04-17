<?php
/**
 * InfinitePayConfig
 *
 * @version    8.4
 * @package    model
 * @subpackage barbearia
 */
class InfinitePayConfig extends TRecord
{
    const TABLENAME  = 'infinitepay_configuracao';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('client_id');
        parent::addAttribute('client_secret_enc');
        parent::addAttribute('access_token_enc');
        parent::addAttribute('refresh_token_enc');
        parent::addAttribute('token_expires_at');
        parent::addAttribute('ativo');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    /**
     * Get the active InfinitePay configuration (singleton per tenant)
     * @return InfinitePayConfig|null
     */
    public static function getInstance()
    {
        try
        {
            $criteria = new TCriteria;
            $criteria->add(new TFilter('ativo', '=', 'Y'));
            $criteria->setProperty('order', 'id asc');
            $criteria->setProperty('limit', 1);

            $repository = new TRepository('InfinitePayConfig');
            $result = $repository->load($criteria, false);

            return ($result && count($result) > 0) ? $result[0] : null;
        }
        catch (Exception $e)
        {
            return null;
        }
    }

    /**
     * Check if InfinitePay is configured
     * @return bool
     */
    public static function isConfigurado()
    {
        $config = self::getInstance();
        return !empty($config) && !empty($config->client_id) && !empty($config->client_secret_enc);
    }
}
