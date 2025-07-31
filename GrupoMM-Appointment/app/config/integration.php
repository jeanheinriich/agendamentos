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
 * As configurações de integração com outros sistemas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

// Configurações de integração com outros sistemas
return [ 'integration' => [
    'fipe' => [
      // O endereço da conexão ao serviço REST
      'url' => 'https://fipe.parallelum.com.br/api/v1',
      // O método HTTP a ser utilizado
      'method' => 'GET',
      // O local de armazenamento de arquivos para o sistema de
      // integração
      'path' => $app->getCacheDir() . '/integration/fipe/'
    ],
    'stc' => [
      // O endereço da conexão ao serviço REST
      //'url' => 'http://ap3.stc.srv.br/integration/sandbox',
      'url' => 'http://ap3.stc.srv.br/integration/prod',
      // O método HTTP a ser utilizado
      'method' => 'POST',
      // O local de armazenamento de arquivos para o sistema de
      // integração
      'path' => $app->getCacheDir() . '/integration/stc/'
    ],
    'viacep' => [
      // O endereço da conexão ao serviço REST
      'url' => 'https://viacep.com.br/ws',
      // O método HTTP a ser utilizado
      'method' => 'GET',
      // O local de armazenamento de arquivos para o sistema de
      // integração
      'path' => $app->getCacheDir() . '/integration/viacep/'
    ],
    'indicators' => [
      // O provedor dos dados de indicadores financeiros
      'provider' => 'ValorConsultingProvider',
      // O local de armazenamento de arquivos para o sistema de
      // integração
      'path' => $app->getCacheDir() . '/integration/indicators/'
    ]
  ]
];
