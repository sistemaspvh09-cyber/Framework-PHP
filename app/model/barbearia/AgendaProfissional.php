<?php
/**
 * AgendaProfissional
 *
 * @version    8.4
 * @package    model
 * @subpackage barbearia
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AgendaProfissional extends TRecord
{
    const TABLENAME  = 'agenda_profissional';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('profissional_id');
        parent::addAttribute('dia_semana');
        parent::addAttribute('hora_inicio');
        parent::addAttribute('hora_fim');
        parent::addAttribute('intervalo_minutos');
        parent::addAttribute('ativo');
    }

    /**
     * Return profissional
     */
    public function get_profissional()
    {
        return Profissional::find($this->profissional_id);
    }
}
