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
 * As configurações dos containers Middlewares.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Config;

use App\Middlewares\AuthenticatedMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\PublicMiddleware;
use Core\Sessions\SessionMiddleware;
use Core\Middlewares\CacheMiddleware;
use Core\Middlewares\FieldsTrimmer;
use Core\Middlewares\TrailingSlashMiddleware;
use Core\Middlewares\HttpMethodOverrideMiddleware;
use Core\Middlewares\ConsoleMiddleware;
use RKA\Middleware\IpAddress;

if (PHP_SAPI == 'cli') {
  // ===================================================================
  // Adiciona o middleware de execução das tarefas em linha de comando
  // para todas as rotas do sistema, independente da rota.
  // ===================================================================

  // Registra o sistema de execução de tarefas em linha de comando
  $app->add(new ConsoleMiddleware($container,
    $container['commands']));
} else {
  // ===================================================================
  // Adiciona os middlewares de aplicação, que são executados para todas
  // as rotas do sistema, independente da rota.
  // ===================================================================

  // Registra o sistema de gerenciamento de sessão
  $app->add(new SessionMiddleware($container));

  // Registra o sistema de normalização de rotas
  $app->add(new TrailingSlashMiddleware($container));

  // Registra o sistema de normalização de rotas
  $app->add(new HttpMethodOverrideMiddleware($container));

  // Registra o identificador de IPs
  $checkProxyHeaders = false;
  $trustedProxies = [];
  $app->add(new IpAddress($checkProxyHeaders, $trustedProxies));

  // =====================================================================
  // Adiciona os middlewares de rotas, que são executados para rotas
  // específicas conforme estipulado nas configurações de rotas
  // =====================================================================

  // Registra o middleware de área pública que é responsável por
  // disponibilizar páginas em rotas públicas
  $container['public.middleware'] = function ($container) {
    return new PublicMiddleware($container);
  };

  // Registra o middleware de conta convidado (guest) que é responsável
  // por verificar se o usuário não está autenticado no sistema
  $container['guest.middleware'] = function ($container) {
    return new GuestMiddleware($container);
  };

  // Registra o middleware de autorização que é responsável por verificar
  // se o usuário está autenticado no sistema e se possui as devidas
  // permissões para a rota em questão
  $container['auth.middleware'] = function ($container) use ($app) {
    return new AuthenticatedMiddleware($container);
  };

  // Registra o middleware de Auto Trimmer que é responsável por eliminar
  // espaços desnecessários no começo, meio e final de campos de texto
  // para a rota em questão
  $container['trimmer'] = function ($container) use ($app) {
    return new FieldsTrimmer($container);
  };

  // Registra o middleware de cache que é responsável por permitir o
  // cache à nível de navegador para determinadas rotas
  $container['cache'] = function ($container) use ($app) {
    return new CacheMiddleware($container);
  };
}
