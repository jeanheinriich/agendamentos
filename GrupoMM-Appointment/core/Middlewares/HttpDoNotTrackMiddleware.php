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
 * Middleware responsável pela interpretação do Header para sinalizar
 * que o cliente deseja não ser rastreado.
 */

namespace Core\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpDoNotTrackMiddleware
  extends Middleware
{
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
    $doNotTrack = false;

    // Determina se o cabeçalho contém a informação de que o cliente não
    // deseja ser rastreado
    if ($request->hasHeader('HTTP_DNT')) {
      $doNotTrack = intval($request->getHeaderLine('HTTP_DNT'))==1?true:false;
    } elseif ($request->hasHeader('HTTP_X_DO_NOT_TRACK')) {
      $doNotTrack = intval($request->getHeaderLine('HTTP_X_DO_NOT_TRACK'))==1?true:false;
    }

    // TODO: Aqui devemos processar a informação.
    
    // Registra o evento de depuração
    $action = $doNotTrack?"deseja":"NÃO deseja";
    $this->debug("O requisitante {action} ser rastreado",
      [ 'action' => $action ]
    );

    // Prossegue normalmente
    return $next($request, $response);
  }
}
