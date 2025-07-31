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
 * As configurações do container de controle de sessão.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Sessions\SessionManager;

// Session
// O sistema de gerenciamento de sessão
$container['session'] = function ($container) {
  return new SessionManager();
};