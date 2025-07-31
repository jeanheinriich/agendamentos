<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * O manipulador de retorno de chamada de rota padrão com parâmetros de
 * rota como uma matriz de argumentos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\InvocationStrategyInterface;

class FoundHandler
  extends AbstractHandler
  implements InvocationStrategyInterface
{
  /**
   * Invoca uma rota que pode ser chamada com solicitação, resposta e
   * todos os parâmetros de rota como uma matriz de argumentos.
   *
   * @param array|callable $callable
   *   Um objeto PHP chamável válido
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param array $routeArguments
   *   Os argumentos da rota solicitada
   *
   * @return ResponseInterface
   */
  public function __invoke(
      callable $callable,
      ServerRequestInterface $request,
      ResponseInterface $response,
      array $routeArguments
  ) {
    foreach ($routeArguments as $k => $v) {
      $request = $request->withAttribute($k, $v);
    }

    // Determina o endereço atual
    $uri    = $request->getUri();
    $path   = $uri->getPath();
    $params = $request->getQueryParams();

    // Verifica o método da solicitação
    $method = $request->getMethod();

    // Registra o sucesso no log
    if ($this->has('logger')) {
      $this->debug("Requisitado '{path}' usando o método HTTP {method}",
        [ 'path' => ltrim($path, '/'),
          'method' => $method ]
      );
    }

    return call_user_func($callable, $request, $response,
      $routeArguments)
    ;
  }
}
