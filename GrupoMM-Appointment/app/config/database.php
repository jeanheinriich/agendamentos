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
 * As configurações da conexão com o banco de dados do aplicativo de ERP
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * Notas:
 * 
 * Para acesso por terminal, usar a seguinte linha de comando:
 * psql -h erpgrupomm.postgresql.dbaas.com.br -U erpgrupomm erpgrupomm
 * 
 */

return [
  'database' => [
    'erp' => [
      'driver' => 'pgsql',
      // ---------------------------[ Configuração para uso local ]-----
      //   Aqui são especificados as configurações quando o servidor do
      // banco de dados está hospedado na mesma máquina que o aplicativo
      // 
      'host' => 'localhost',
      'database' => 'erp',
      'username' => 'admin',
      'password' => '#d3vj34n#',
      // ------------------[ Configuração em ambiente de produção ]-----
      //   Aqui são especificados as configurações quando o servidor do
      // banco de dados está hospedado em outro servidor
      // 
      // 'host' => '10.128.0.3',
      // 'database' => 'erp',
      // 'username' => 'admin',
      // 'password' => 'M4r!0M!r4nd4',
      // ---------------------------------------------------------------
      'charset'  => 'utf8',
      'prefix'   => '',
      'schema'   => 'erp'
    ]
  ]
];
