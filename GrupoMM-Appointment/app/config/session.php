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
 * As configurações de armazenamento de dados em sessão.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'session' => [
    // O prefixo para o nome do cookie onde são armazenadas as
    // informações de sessão
    'prefix'      => 'GrupoMM',

    // O sufixo para o nome do cookie onde são armazenadas as
    // informações de sessão
    'suffix'      => 'Data',

    // O tempo de vida do cookie de sessão (em minutos). Default: 2h
    //'lifetime'  => 60*2,
    //'lifetime'    => '2 hour',
    'lifetime'    => '20 minutes',

    // Estende automaticamente o tempo de vida da sessão
    'autorefresh' => true,

    // O caminho onde os arquivos de sessão são armazenados. Caso o
    // valor seja nulo, o caminho padrão do PHP será utilizado
    'save_path' => $app->getSessionDir(),

    // Definir o nome, caminho do cookie da sessão, domínio e segurança
    // automaticamente
    'cookie_autoset' => true,
    // Definir o nome, caminho do cookie da sessão, domínio e segurança
    // manualmente
    // 'cookie_name' => 'Session',
    // 'path'        => '/',
    // 'secure'      => isset($_SERVER['HTTPS'])?($_SERVER['HTTPS']==='on'?true:false):false,
    // 'httponly'    => true

    // Nossa chave de criptografia dos dados de sessão
    'salt' => 'e3b86cbc81f1dcebf8f95782e4987'
  ]
];
