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
 * As configurações do container de controle do sistema de registro de
 * eventos (logs)
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Logger\Processor\AccountProcessor;
use Core\Logger\Processor\InterpolateProcessor;
use Core\Logger\Processor\IntrospectionProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

// Logger
// O sistema de registro de logs
$container['logger'] = function ($container) {
  $config = $container['settings']['logger'];

  // Determina um formatador de linha
  $format = "[%datetime%][%level_name%][%extra.class%] %message% %context%%extra%\n";
  $formatter = new LineFormatter($format, null, false, true);

  // Cria um manipulador único do sistema de log, que irá registrar
  // todas as mensagens em um único arquivo (independente do nível)
  $rotatingFileHandler = new RotatingFileHandler($config['path'],
    $config['maxFiles'], $config['level'])
  ;
  $rotatingFileHandler->setFilenameFormat('{date}', 'Ymd');
  $rotatingFileHandler->setFormatter($formatter);

  // Cria o sistema de log
  $logger = new Logger($config['name']);
  $authorization = null;
  if ($container->has('authorization')) {
    $authorization = $container->get('authorization');
  }
  $logger->pushProcessor(new AccountProcessor($authorization));
  $logger->pushProcessor(new IntrospectionProcessor());
  $logger->pushProcessor(new InterpolateProcessor());
  $logger->pushHandler($rotatingFileHandler);

  return $logger;
};