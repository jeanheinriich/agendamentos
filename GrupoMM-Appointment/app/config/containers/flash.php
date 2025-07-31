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
 * As configurações do container de controle de mensagens flash.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Flash\Messages AS FlashMessage;

// Flash messages
// O sistema de mensagens Flash
$container['flash'] = function ($container) {
  // Usa um armazenamento de dados em matriz até que o Middleware de
  // sessão seja iniciado
  $storage = [];

  return new FlashMessage($storage);
};