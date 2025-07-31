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
 * Middleware responsável pela correção do método para requisições que
 * utilizem os métodos PUT, DELETE e PATCH.
 */

namespace Core\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpMethodOverrideMiddleware
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
  public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
  )
  {
    // Recupera os detalhes do endereço da requisição
    $this->parseURI($request);

    // Quando o método for POST, altera-o para o método correto
    if ($this->HTTPMethod === 'POST') {
      if ($this->parameters) {
        // Recupera o campo método, se existir, permitindo a mudança do
        // método (Ex.: para PUT)
        if (array_key_exists('_method', $this->parameters)) {
          // Substitui o método de solicitação HTTP com um cabeçalho de
          // solicitação HTTP X-Http-Method-Override personalizado
          $this->HTTPMethod = $this->parameters['_method'];
          $request = $request->withMethod($this->HTTPMethod);

          // Registra o evento
          $this->debug("Corrigindo para [{method}] {route}",
            [ 'method' => $this->HTTPMethod,
              'route' => $this->uri ]
          );
        }
      }
    }

    // Prossegue normalmente
    return $next($request, $response);
  }
}
