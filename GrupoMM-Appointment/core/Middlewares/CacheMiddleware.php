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
 * Middleware responsável pela manipulação de rotas para manipular os
 * cabeçalhos de configuração do cache HTTP em conteúdos estáticos. Este
 * tratamento permite ao browser lidar corretamente com o cache local da
 * página estática.
 */

namespace Core\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
  extends Middleware
{
  /**
   * A função executada sempre que o middleware for chamado. Responsável
   * por manipular os cabeçalhos para controle do cache no lado do
   * cliente.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface
   *   $response A resposta HTTP
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

    // Somente devemos lidar com cache em requisições do tipo GET
    if ($this->HTTPMethod === 'GET') {
      // Primeiramente, obtemos o conteúdo da resposta
      $response = $next($request, $response);

      // Recupera as configurações de cache
      if ($this->has('settings')) {
        $settings = $this->settings['cache'];
      } else {
        // Carregamos as configurações padrão
        $settings = [
          'type'   => 'private',
          'max-age' => 86400,
          'must-revalidate' => false
        ];
      }

      // Lidamos com as informações do cabeçalho Cache-Control
      if (!$response->hasHeader('Cache-Control')) {
        // Ainda não temos o controle de cache adicionado
        
        // Verifica a idade máxima
        if ($settings['max-age'] === 0) {
          // Não estamos armazenando o conteúdo em cache, então passa isto
          // no cabeçalho da resposta
          $response = $response->withHeader(
            'Cache-Control',
            sprintf(
              '%s, no-cache%s',
              $settings['type'],
              $settings['must-revalidate'] ? ', must-revalidate' : ''
            )
          );

          // Registra o evento
          $this->debug("O cache está desabilitado para o conteúdo",
            [ 'method' => $this->HTTPMethod,
              'route' => $this->uri ]
          );
        } else {
          // Adicionamos o tempo de vida do conteúdo em cache
          $response = $response->withHeader(
            'Cache-Control',
            sprintf(
              '%s, max-age=%s%s',
              $settings['type'],
              $settings['max-age'],
              $settings['must-revalidate'] ? ', must-revalidate' : ''
            )
          );

          // Registra o evento
          $maxAge = $settings['max-age'];
          $this->debug("Cache do tipo '{$settings['type']}'' com tempo "
            . "máximo de " . $this->format($maxAge) . " ({$maxAge}s) "
            . "habilitado para o conteúdo",
            [ 'method' => $this->HTTPMethod,
              'route' => $this->uri ]
          );
        }
      }

      // Lida com o cabeçalho ETag
      $etag = $response->getHeader('ETag');
      $etag = reset($etag);

      if ($etag) {
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');

        if ($ifNoneMatch) {
          $etagList = preg_split('@\s*,\s*@', $ifNoneMatch);
          if ( is_array($etagList) && (in_array($etag, $etagList)
               || in_array('*', $etagList))) {
            // Registra o evento
            $this->debug("Retornando conteúdo com o status HTTP de "
              . "'304 - Não modificado'",
              [ 'method' => $this->HTTPMethod,
                'route' => $this->uri ]
            );

            return $response
              ->withStatus(304)
            ;
          }
        }
      }

      // Lida com o cabeçalho Last-Modified
      $lastModified = $response->getHeaderLine('Last-Modified');

      if ($lastModified) {
        if (!is_numeric($lastModified)) {
          $lastModified = strtotime($lastModified);
        }

        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');

        if ($ifModifiedSince && $lastModified <= strtotime($ifModifiedSince)) {
          // Registra o evento
          $this->debug("Retornando conteúdo com o status HTTP de "
            . "'304 - Não modificado'",
            [ 'method' => $this->HTTPMethod,
              'route' => $this->uri ]
          );

          return $response
            ->withStatus(304)
          ;
        }
      }

      return $response;
    }

    // Prossegue normalmente, ignorando quaisquer modificações no
    // cabeçalho
    return $next($request, $response);
  }

  /**
   * Formata o tempo de cache em segundos em um valor de horas, minutos
   * e segundos.
   * 
   * @param int $maxAge
   *   O valor da diferença em segundos
   * @param string $separator
   *   O caractere separador (padrão: ':')
   * 
   * @return string
   */
  protected function format(int $maxAge)
  {
    $days  = floor($maxAge/86400);
    $maxAge = ($maxAge - $days*86400);
    $hours = floor($maxAge/3600);
    $mins  = ($maxAge/60)%60;
    $secs  = $maxAge%60;

    $result = ''
      . (($days > 0) ? $days . (($days > 1) ? ' dias ':' dia ') : '')
      . (($hours > 0) ? $hours . 'h ' : '')
      . (($mins > 0) ? $mins . 'm ' : '')
      . (($secs > 0) ? $secs . 's ' : '')
    ;
    
    return trim($result);
  }
}
