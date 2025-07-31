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
 * As configurações do container de controle da conexão com o banco de
 * dados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

// Database
// O controlador do sistema de Banco de Dados
$capsule = new Capsule();
$capsule->addConnection($container['settings']['database']['erp'], 'erp');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container['DB'] = function ($container) use ($capsule){
  return $capsule::connection('erp');
};
