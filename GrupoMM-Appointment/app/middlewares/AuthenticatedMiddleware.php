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
 * Middleware responsável pela manipulação de rotas privadas em que o
 * usuário deve estar autenticado.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Middlewares;

use Core\Middlewares\AuthorizedTrait;
use Core\Middlewares\Middleware;
use Core\Flash\FlashTrait;
use Core\Traits\ExceptionTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class AuthenticatedMiddleware
  extends Middleware
{
  /**
   * Os métodos para manipulação da autorização
   */
  use AuthorizedTrait;

  /**
   * Os métodos para manipulação de erros
   */
  use ExceptionTrait;
  
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
  public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
  )
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

    // Verifica se o usuário está autenticado e não possui bloqueios
    if ($this->hasLoggedIn()) {
      // Verifica se o usuário possui autorização para a rota
      if ($this->hasAuthorizationFor($this->routeName, $this->HTTPMethod)) {
        // O acesso à rota foi liberado
        
        // Registra o evento
        $this->debug("Liberado acesso à '{routeName}' usando o método "
          . "HTTP {method}",
          [ 'routeName' => $this->routeName,
            'method' => $this->HTTPMethod ]
        );
        
        // Prossegue normalmente
        return $next($request,$response);
      } else {
        // Recupera o erro
        $error = $this->getError();

        // Registra o evento
        $this->warning($error['message']);

        // Conforme o tipo de conteúdo, devolve uma mensagem de erro
        $contentType = $this->determineContentType($request);
        switch ($contentType)
        {
          case 'application/json':
            // Verifica se a requisição foi originada de um DataTable
            $params = $request->getParams();
            if (array_key_exists("draw", $params)) {
              // Devolve uma mensagem de erro em JSON formatada no
              // padrão do DataTable como resultado
              return $response
                ->withHeader('Content-type',
                    $this->determineContentType($request)
                  )
                ->withJson([
                    'draw' => $params['draw'],
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'message' => $error['humanizedMessage'],
                    'error' => $error['humanizedMessage']
                  ])
              ;
            } else {
              // Devolve uma mensagem de erro em JSON genérica como
              // resultado
              return $response
                ->withHeader('Content-type',
                    $this->determineContentType($request)
                  )
                ->withJson([
                    'result' => 'NOK',
                    'params' => $request->getQueryParams(),
                    'message' => $error['humanizedMessage'],
                    'data' => []
                  ])
                ->withStatus(403)
              ;
            }
            
            break;
          default:
            // Cria uma exceção para informar o erro
            $this->accessDeniedException($request, $response,
              $error['humanizedMessage']);
            
            break;
        }
      }
    } else {
      // Recupera o erro
      $error = $this->getError();

      // Registra o evento
      $this->warning("Bloqueado acesso à '{routeName}' usando o "
        . "método {method}. {errorMessage}.",
        [ 'routeName' => $this->routeName,
          'method' => $this->HTTPMethod,
          'errorMessage' => $error['message'] ]
      );
      
      // Determina o tipo de conteúdo requisitado
      $contentType = $this->determineContentType($request);
      switch ($contentType)
      {
        case 'application/json':
          // Verifica se a requisição foi originada de um DataTable
          $params = $request->getParams();
          if (array_key_exists("draw", $params)) {
            // Devolve uma mensagem de erro em JSON formatada no padrão
            // do DataTable como resultado
            return $response
              ->withHeader('Content-type',
                  $this->determineContentType($request)
                )
              ->withJson([
                  'draw' => $params['draw'],
                  'recordsTotal' => 0,
                  'recordsFiltered' => 0,
                  'data' => [],
                  'error' => $error['humanizedMessage']
                ])
            ;
          } else {
            // Devolve uma mensagem de erro em JSON genérica como
            // resultado
            return $response
              ->withHeader('Content-type',
                  $this->determineContentType($request)
                )
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getQueryParams(),
                  'message' => $error['humanizedMessage'],
                  'data' => []
                ])
            ;
          }
          
          break;
        case 'text/html':
          // Armazena a rota para posterior redirecionamento
          $this->session->set('redirectTo', [
            'name' => $this->routeName,
            'args' => $this->arguments
          ]);
          
          // O usuário não está autenticado, então faz o direcionamento
          // para a página de login de acordo com a parte do sistema em
          // que ele se encontra
          $loginRouteName = $this->determineLoginAddress($this->uri);

          // Acrescenta uma mensagem de alerta
          $this->flashNow('error', $error['humanizedMessage']);
      
          // Registra o evento
          $this->debug("Redirecionando para '{routeName}' usando o "
            . "método HTTP {method}",
            [ 'routeName' => $loginRouteName,
              'method' => $this->HTTPMethod ]
          );
          
          return $this->redirect($response, $loginRouteName);
          
          break;
        default:
          throw new UnexpectedValueException("Não é possível processar "
            . "o tipo de conteúdo desconhecido '{$contentType}'")
          ;
      }
    }
  }
}
