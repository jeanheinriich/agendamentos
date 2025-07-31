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
 * As configurações do container de controle do sistema de envio de
 * e-mails.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Mailer\Mailer;

// Mailer
// O sistema de envio de e-mails
$container['mailer'] = function($container) {
  // Configuramos o serviço de e-mail
  $twig = $container['renderer'];
  $config = $container['settings']['mailer'];
  $mailer = new Mailer($twig, [
    'host'      => $config['account']['host'],       // SMTP Host
    'port'      => $config['account']['port'],       // SMTP Port
    'username'  => $config['account']['username'],   // SMTP Username
    'password'  => $config['account']['password'],   // SMTP Password
    'protocol'  => isset($config['account']['protocol'])
      ? $config['account']['protocol']  // SSL or TLS
      : 'SSL'
  ]);

  // Definimos os detalhes do remetente padrão
  $mailer->setDefaultFrom($config['account']['email'],
    $config['account']['name'])
  ;

  // Definimos o local onde estão armazenadas as imagens embutidas
  $mailer->setPathForEmbedImages($config['pathForEmbedImages']);

  return $mailer;
};
