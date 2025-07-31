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
 * As configurações dos serviços.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [
  // Configurações dos comandos disponíveis para execução
  '__default' => \App\Commands\HelpCommand::class,
  'Help' => \App\Commands\HelpCommand::class,
  'Sync' => \App\Commands\SyncCommand::class,
  'Shippingfile' => \App\Commands\ShippingFileCommand::class,
  'Returnfile' => \App\Commands\ReturnFileCommand::class,
  'Getagencydata' => \App\Commands\GetDunningAgencyDataCommand::class,
  'Import' => \App\Commands\ImportCommand::class,
  'Batchuser' => \App\Commands\BatchUserCommand::class
];
