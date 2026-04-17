<?php
/**
 * ProfissionalServico
 *
 * @version    8.4
 * @package    model
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class ProfissionalServico extends TRecord
{
    const TABLENAME  = 'profissional_servico';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('profissional_id');
        parent::addAttribute('servico_id');
    }

    /**
     * Return profissional
     */
    public function get_profissional()
    {
        return Profissional::find($this->profissional_id);
    }

    /**
     * Return servico
     */
    public function get_servico()
    {
        return Servico::find($this->servico_id);
    }
}
