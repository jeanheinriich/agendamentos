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
 * As configurações dos dados de contatos usados no sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'addresses' => [
    # Desenvolvimento
    'contact' => [
      'name' => 'Contato do sistema',
      'email' => 'emersoncavalcanti@gmail.com'
    ],
    # Produção
    # 'contact' => [
    #   'name' => 'Contato - Grupo M&M',
    #   'email' => 'contato@grupomm.srv.br'
    # ],
    'support' => [
      'name' => 'Suporte - Grupo M&M',
      'email' => 'suporte@grupomm.srv.br'
    ],
    'whatsapp' => [
      'name' => 'Contato por Whatsapp',
      'phone' => '5511932391515',
      'number' => '(11) 93239-1515',
      'message'   => "Olá! Eu gostaria de saber mais sobre o Grupo "
        . "M&amp;M e como ele pode me ajudar. Você pode me ajudar?"
    ],
    'fixo' => [
      'name' => 'Contato por telefone',
      'phone' => '551126589104',
      'number' => '(11) 2658-9104'
    ]
  ]
];
