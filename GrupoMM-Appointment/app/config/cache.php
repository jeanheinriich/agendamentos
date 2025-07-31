<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * As configurações de cache HTTP em páginas estáticas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'cache' => [
    'type'   => 'private',
    'max-age' => 86400,
    'must-revalidate' => false
  ]
];
