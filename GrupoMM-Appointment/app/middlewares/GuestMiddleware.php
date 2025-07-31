<?php
/*
 * This file is part of Extension Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Middleware responsável pela manipulação de rotas em que o usuário não
 * deve estar autenticado.
 */

namespace App\Middlewares;

use Core\Middlewares\Middleware;
use Core\Flash\FlashTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuestMiddleware
  extends Middleware
{
  /**
   * Os métodos para envios de mensagem flash
   */
  use FlashTrait;

  /**
   * A função executada sempre que o middleware for chamado.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param callable $next
   *   O próximo middleware
   * 
   * @return ResponseInterface
   */
  public function __invoke(ServerRequestInterface $request,
    ResponseInterface $response, callable $next)
  {
    // Recupera os dados da rota requisitada
    $this->parseURI($request);
    $this->parseRoute($request);

    // Registra o evento de depuração
    $this->debug("Requisitado '{routeName}' usando o método HTTP "
      . "{method}",
      [ 'routeName' => $this->routeName,
        'method' => $this->HTTPMethod ]
    );
    
    // Verifica se o usuário já está autenticado
    if ($this->authorization->hasLoggedIn()) {
      // Registra o erro
      $this->info("Negado tentativa de novo login para usuário "
        . "já autenticado.", [ ]
      );

      // O usuário já está autenticado, então faz o direcionamento para
      // a página inicial de acordo com a parte do sistema em que ele se
      // encontra
      $routeName = $this->determineHomeAddress($this->uri);

      // Acrescenta uma mensagem de alerta
      $this->flash('warning', "Você já se encontra autenticado.");
      
      // Registra o evento
      $this->debug("Redirecionando para '{routeName}' usando o " .
        "método HTTP {method}",
        [ 'routeName' => $routeName,
          'method' => $this->HTTPMethod ]
      );
      
      return $this->redirect($response, $routeName);
    }
    
    // Prossegue normalmente
    return $next($request, $response);
  }
}
