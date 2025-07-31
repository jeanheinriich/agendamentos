<?php
/*
 * Este arquivo é parte do sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * O inicializador do aplicativo que responde pela execução de rotinas
 * em linha de comando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

use App\Console;

if (PHP_SAPI == 'cli') {
  session_cache_limiter('0');
  session_start();

  // A reportagem de erros (Error reporting)
  error_reporting(E_ALL ^ E_DEPRECATED ^ E_STRICT);
  //ini_set('display_errors', '0');
  //ini_set('display_startup_errors', '0');

  // Define o fuso horário padrão
  date_default_timezone_set('America/Sao_Paulo');

  $loader = require __DIR__ . '/../vendor/autoload.php';

  // Instancia e executa o aplicativo
  $console = new Console();
  $console->setPublicDir(dirname($_SERVER["SCRIPT_FILENAME"]));
  $console->run();
} else {
  die("ERRO:\nVocê está tentando executar um aplicativo desenvolvido "
    . "para execução em modo console num ambiente Web\n\n");
}
