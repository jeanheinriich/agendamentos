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
 * As configurações de armazenamento de dados em cookies.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'cookie' => [
    // Nossa chave de criptografia (salt)
    'salt'    => 'e3b86cbc81f1dcebf8f95782e4987',
    // Os parâmetros do nosso cookie
    'options' => [
      // O prefixo do nome do cookie onde são armazenadas informações
      'prefix'  => 'GrupoMM',
      // O caminho no domínio onde o cookie vai funcionar
      'path'     => '/',
      // A segurança do cookie
      'secure'   => isset($_SERVER['HTTPS'])?($_SERVER['HTTPS']==='on'?true:false):false,
      // Restrição para Javascript
      'httponly' => true
    ]
  ]
];
