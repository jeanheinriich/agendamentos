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
 * O inicializador do aplicativo que responde pela exibição das páginas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use App\Application;

if (PHP_SAPI == 'cli-server') {
  // Verificamos se a solicitação foi realmente para algo que
  // provavelmente deve ser servido como um arquivo estático, pois
  // se encontra na pasta pública
  $url  = parse_url($_SERVER['REQUEST_URI']);
  $file = __DIR__ . $url['path'];
  
  if (is_file($file))
  {
    return false;
  }
}

// Define o fuso horário padrão
date_default_timezone_set('America/Sao_Paulo');

$loader = require __DIR__ . '/../vendor/autoload.php';

// Instancia e executa o aplicativo
$app = new Application();
$app->setPublicDir(getcwd());
$app->run();
