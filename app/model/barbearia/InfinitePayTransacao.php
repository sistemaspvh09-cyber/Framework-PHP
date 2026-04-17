<?php
/**
 * InfinitePayTransacao
 *
 * @version    8.4
 * @package    model
 * @subpackage barbearia
 */
class InfinitePayTransacao extends TRecord
{
    const TABLENAME  = 'infinitepay_transacao';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('agendamento_id');
        parent::addAttribute('charge_id');
        parent::addAttribute('valor');
        parent::addAttribute('status');
        parent::addAttribute('webhook_payload');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    /**
     * Return agendamento
     */
    public function get_agendamento()
    {
        return Agendamento::find($this->agendamento_id);
    }

    /**
     * Find transaction by external charge ID
     * @param string $chargeId
     * @return InfinitePayTransacao|null
     */
    public static function findByChargeId($chargeId)
    {
        try
        {
            $criteria = new TCriteria;
            $criteria->add(new TFilter('charge_id', '=', $chargeId));
            $criteria->setProperty('limit', 1);

            $repository = new TRepository('InfinitePayTransacao');
            $result = $repository->load($criteria, false);

            return ($result && count($result) > 0) ? $result[0] : null;
        }
        catch (Exception $e)
        {
            return null;
        }
    }

    /**
     * Find latest transaction by agendamento ID
     * @param int $agendamentoId
     * @return InfinitePayTransacao|null
     */
    public static function findByAgendamentoId($agendamentoId)
    {
        try
        {
            $criteria = new TCriteria;
            $criteria->add(new TFilter('agendamento_id', '=', (int) $agendamentoId));
            $criteria->setProperty('order', 'id desc');
            $criteria->setProperty('limit', 1);

            $repository = new TRepository('InfinitePayTransacao');
            $result = $repository->load($criteria, false);

            return ($result && count($result) > 0) ? $result[0] : null;
        }
        catch (Exception $e)
        {
            return null;
        }
    }
}
