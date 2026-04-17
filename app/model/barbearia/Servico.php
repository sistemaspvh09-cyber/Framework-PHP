<?php
/**
 * Servico
 *
 * @version    8.4
 * @package    model
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class Servico extends TRecord
{
    const TABLENAME  = 'servico';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('nome');
        parent::addAttribute('preco');
        parent::addAttribute('duracao_minutos');
        parent::addAttribute('ativo');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
