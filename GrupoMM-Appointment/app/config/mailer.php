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
 * As configurações de envio de e-mail.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'mailer' => [
    'account' => [
      # Desenvolvimento
      'host' => 'smtp.office365.com',
      'port' => 587,
      'name' => 'Emerson Cavalcanti',
      'email' => 'emcvlt@hotmail.com',
      'username' => 'emcvlt@hotmail.com',
      'password' => '#t3l3c0m#',
      'protocol' => 'tls',
      # Produção
      # 'host' => 'smtp.grupomm.srv.br',
      # 'port' => 587,
      # 'name' => 'Grupo M&M',
      # 'email' => 'noreply@grupomm.srv.br',
      # 'username' => 'noreply@grupomm.srv.br',
      # 'password' => 'YMt2020$',
      # Demais configurações
      'stream' => [
        'ssl' => [
          'allow_self_signed' => true,
          'verify_peer' => false,
          'verify_peer_name' => false
        ]
      ]
    ],
    'sender' => [
      # Desenvolvimento
      'name' => 'Emerson Cavalcanti',
      'email' => 'emcvlt@hotmail.com',
      'signature' => [
        'fullname' => 'Fagundes Varela Rastreamento - EPP',
        // Opcional
        'jobtitle' => null,
        'contacts' => [
          // O nome do tipo de contato e o número ou texto
          'WhatsApp' => '(11) 98450-3639',
          'Fixo' => '(11) 3456-7890'
        ]
      ]
      # Produção
      # 'name' => 'Grupo M&M Rastreamento',
      # 'email' => 'noreply@grupomm.srv.br',
      # 'signature' => [
      #   'fullname' => 'M.S. de Miranda Rastreadores - EPP',
      #   // Opcional
      #   'jobtitle' => null,
      #   'contacts' => [
      #     // O nome do tipo de contato e o número ou texto
      #     'WhatsApp' => '(11) 93239-1515',
      #     'Fixo' => '(11) 2658-9104'
      #   ]
      # ]
    ],
    'pathForEmbedImages' => $app->getStorageDir() . DIRECTORY_SEPARATOR . 'mailer'
  ]
];
