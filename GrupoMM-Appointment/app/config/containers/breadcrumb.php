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
 * As configurações do container de controle das trilhas de navegação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Breadcrumbs\Breadcrumb;

// Breadcrumb
// O sistema de geração das trilhas de navegação
$container['breadcrumb'] = function ($container) {
  return new Breadcrumb($container);
};
