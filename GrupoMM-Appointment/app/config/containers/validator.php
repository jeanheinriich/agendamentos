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
 * As configurações do container de controle do sistema de validação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Validation\Validator;
use Respect\Validation\Validator as V;

// Validator
// O sistema de validações
$container['validator'] = function ($container) {
  return new Validator();
};
// Adiciona as regras de validação definidas internamente
V::with('Core\\Validation\\Rules\\');
