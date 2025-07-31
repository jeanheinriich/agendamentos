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
 * O inicializador dos manipuladores do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Config;

use Core\Exceptions\AccessDeniedException;
use Core\Handlers\AccessDeniedHandler;
use Core\Handlers\CSRFFailureHandler;
use Core\Handlers\ErrorHandler;
use Core\Handlers\FoundHandler;
use Core\Handlers\NotAllowedHandler;
use Core\Handlers\NotFoundHandler;
use Core\Handlers\PhpErrorHandler;
use Exception;
use Slim\Handlers\PhpError;
use Slim\Handlers\Strategies\RequestResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Manipula os eventos de rotas encontradas
 */
$container['foundHandler'] = function ($container) {
  $displayErrorDetails = $this->isDevelopment()
    ? true
    : false
  ;

  return new FoundHandler($displayErrorDetails, $container);
};

/**
 * Manipula os erros de CSRF (Cross-Site Request Forgery)
 */
if (PHP_SAPI !== 'cli') {
  $container['CSRFFailureHandler'] = function ($container) {
    return function (ServerRequestInterface $request,
      ResponseInterface $response)
      use ($container)
    {
      $displayErrorDetails = $this->isDevelopment()
        ? true
        : false
      ;
      
      // Realiza o tratamento com o manipulador de falha de CSRF
      $csrfFailureHandler = new CSRFFailureHandler($displayErrorDetails,
        $container)
      ;

      return $csrfFailureHandler($request, $response);
    };
  };
}

/**
 * Manipula os erros de método não permitido para uma rota numa
 * requisição HTTP
 */
$container['notAllowedHandler'] = function ($container) {
  return function (ServerRequestInterface $request,
    ResponseInterface $response, $methods)
    use ($container)
  {
    $displayErrorDetails = $this->isDevelopment()
      ? true
      : false
    ;
    
    // Realiza o tratamento com o manipulador de método não permitido
    $notAllowedHandler = new NotAllowedHandler($displayErrorDetails,
      $container)
    ;

    return $notAllowedHandler($request, $response, $methods);
  };
};

/**
 * Manipula os erros de rota não encontrada
 */
unset($container['notFoundHandler']);
$container['notFoundHandler'] = function ($container) {
  return function (ServerRequestInterface $request,
    ResponseInterface $response)
    use ($container)
  {
    $displayErrorDetails = $this->isDevelopment()
      ? true
      : false
    ;
    
    // Realiza o tratamento com o manipulador de página não encontrada
    $notFoundHandler = new NotFoundHandler($displayErrorDetails,
      $container)
    ;

    return $notFoundHandler($request, $response);
  };
};

/**
 * Manipula os erros de acesso negado para uma rota
 */
$container['accessDeniedHandler'] = function ($container) {
  return function (ServerRequestInterface $request,
    ResponseInterface $response, AccessDeniedException $exception)
    use ($container)
  {
    $displayErrorDetails = $this->isDevelopment()
      ? true
      : false
    ;

    // Realiza o tratamento com o manipulador de acesso negado
    $handler = new AccessDeniedHandler($displayErrorDetails, $container);

    return $handler($request, $response, $exception);
  };
};

/**
 * Manipula os erros internos do aplicativo
 */
$container['errorHandler'] = function ($container) {
  return function (ServerRequestInterface $request,
    ResponseInterface $response, Exception $exception)
    use ($container)
  {
    // Se a exceção for de 'Acesso negado', realiza um tratamento
    // diferenciado
    if ($exception instanceof AccessDeniedException) {
      // Transfere o tratamento para o manipulador de acesso negado
      return $container['accessDeniedHandler']($request, $response,
        $exception)
      ;
    }

    $displayErrorDetails = $this->isDevelopment()?true:false;
    
    // Realiza o tratamento com o manipulador de erros
    $errorHandler = new ErrorHandler($displayErrorDetails, $container);

    return $errorHandler($request, $response, $exception);
  };
};

/**
 * Manipula os erros de PHP
 */
$container['phpErrorHandler'] = function ($container) {
  return function (ServerRequestInterface $request,
    ResponseInterface $response, Throwable $error)
    use ($container)
  {
    $displayErrorDetails = $this->isDevelopment()
      ? true
      : false
    ;
    
    // Realiza o tratamento com o manipulador de erros do PHP
    $phpErrorHandler = new PhpErrorHandler($displayErrorDetails,
      $container)
    ;
    return $phpErrorHandler($request, $response, $error);
  };
};
