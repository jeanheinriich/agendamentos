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
 * Middleware responsável pela manipulação de rotas, eliminando a barra
 * final '/' desnecessária no roteamento, eliminando erros.
 */

namespace Core\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class TrailingSlashMiddleware
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
    // Recupera os dados da URI requisitada
    $this->parseURI($request);

    // Normaliza a URL, removendo a barra final do caminho de uma URL
    // (Ex: '/post/23/' é convertido em '/post/23'), evitando que
    // tenhamos problemas com o roteador
    $routePath = $this->normalize($this->uri);

    // Verifica se o path foi modificado
    if ($routePath !== $this->uri) {
      // Registra o evento
      $this->debug("Detectada URL mal formada. Redirecionando "
        . "para [{method}] {route}",
        [ 'method' => $this->HTTPMethod,
          'route' => $routePath ]
      );
      
      // Redirecionamos para a página correta
      return $this->redirectTo($response, $routePath);
    }

    // Prossegue normalmente
    return $next($request, $response);
  }

  /**
   * Normaliza a URL, retirando caracteres inválidos.
   * 
   * @param string $path
   *   A URL a ser normalizada
   * 
   * @return string
   *   A URL normalizada
   */
  private function normalize(string $path): string
  {
    // Se o path não for informado, sempre retorna a página inicial
    if ($path === '') {
      return '/';
    }

    // Analisamos o path
    if (strlen($path) > 1) {
      return rtrim($path, '/');
    }

    return $path;
  }
}
