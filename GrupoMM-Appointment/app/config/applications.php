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
 * As configurações das aplicações registradas. Deve-se configurar ao
 * menos uma aplicação ('/' que é a aplicação raiz). Para cada aplicação
 * deve-se identificar as rotas que são públicas (normalmente as de
 * autenticação do usuário). Caso todas as rotas sejam públicas, então
 * pode-se utilizar o wildcard '*'.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [
  // As configurações de aplicativos e dos respectivos caminhos públicos
  'applications' => [
    // O aplicativo base
    '/' => '*',
    // O módulo administrativo
    '/usr' => [
      'login',
      'forgot',
      'register',
      'reset',
      'sentinstructions'
    ],
    // O módulo administrativo
    '/adm' => [
      'login',
      'forgot',
      'register',
      'sentinstructions'
    ],
    // O aplicativo de ERP
    '/erp' => [
      'login',
      'forgot',
      'register',
      'reset',
      'sentinstructions'
    ],
    // O aplicativo de integração com a STC
    '/stc' => [
      'login',
      'forgot',
      'register',
      'reset',
      'sentinstructions'
    ]
  ]
];
