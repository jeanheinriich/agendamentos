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
 * As configurações do container de controle do sistema de proteção CSRF
 * (do inglês Cross-site request forgery - Falsificação de solicitação
 * entre sites).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Slim\Csrf\Guard;

// Proteção CSRF
// O sistema de proteção CSRF (do inglês Cross-site request forgery -
// Falsificação de solicitação entre sites).
$container['csrf'] = function($container) {
  $csrf = new Guard();
  $csrf->setFailureCallable($container['CSRFFailureHandler']);

  return $csrf;
};
