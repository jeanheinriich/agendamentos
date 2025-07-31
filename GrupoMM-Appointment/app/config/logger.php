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
 * As configurações de registro de eventos em arquivo de log.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

// Configuração do registro de eventos usando o Monolog
return [ 'logger' => [
    'name'  => 'erp',
    'maxFiles'  => $app->environment['developmentMode'] ? 2 : 20,
    'path'  => $app->getLogDir()
      . '/' . $app->getSAPI()
      . '/.log',
    'level' => $app->environment['developmentMode']
      ?(\Monolog\Logger::DEBUG)
      :(\Monolog\Logger::INFO)
  ]
];
