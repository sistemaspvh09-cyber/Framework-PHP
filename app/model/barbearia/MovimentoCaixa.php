<?php
/**
 * MovimentoCaixa
 *
 * @version    8.4
 * @package    model
 * @subpackage barbearia
 */
class MovimentoCaixa extends TRecord
{
    const TABLENAME  = 'movimento_caixa';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('data_movimento');
        parent::addAttribute('tipo');
        parent::addAttribute('descricao');
        parent::addAttribute('valor');
        parent::addAttribute('categoria');
        parent::addAttribute('agendamento_id');
        parent::addAttribute('created_at');
    }

    /**
     * Return agendamento
     */
    public function get_agendamento()
    {
        return Agendamento::find($this->agendamento_id);
    }
}
