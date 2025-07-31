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
 * Middleware responsável pela manipulação de requisições POST e PUT em
 * que se deseje eliminar espaços em branco antes e/ou depois dos campos
 * texto dos formulários.
 */

namespace Core\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// Fragmento de código baseado no trabalho original do framework PHP
// Laravel em que acrescentamos a função str_contains, caso ela não
// exista
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle) {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
  }
}

class FieldsTrimmer
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
    $this->parseRoute($request);

    // Conforme o método, fazemos a análise
    switch ($this->HTTPMethod) {
      case 'POST':
      case 'PUT':
        // Recupera os campos enviados
        $inputs = $request->getParsedBody();

        if ($inputs) {
          // Registra o evento de depuração
          $this->debug("Limpando espaços em branco em campos texto "
            . "na requisição para '{routeName}' usando o método HTTP "
            . "{method}",
            [ 'routeName' => $this->routeName,
              'method' => $this->HTTPMethod ]
          );

          array_walk_recursive($inputs, function (&$item, $key) {
            // Verifica se o item é um campo de texto, mas não é um
            // campo de senha ou de comentários e notas
            if ( is_string($item) &&
                 !str_contains($key, 'password') &&
                 !str_contains($key, 'notes') &&
                 !str_contains($key, 'comments') ) {
              $item = trim(preg_replace('/\s+/', ' ', $item));
            }

            // Elimina valores nulos
            $item = ($item == "") ? null : $item;
          });

          $request = $request->withParsedBody($inputs);
        }

        break;
      
      default:
        // Não faz nada
        
        break;
    }
    
    // Prossegue normalmente
    return $next($request, $response);
  }
}
