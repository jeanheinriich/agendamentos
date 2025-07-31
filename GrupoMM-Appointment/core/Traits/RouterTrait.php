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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipulação de rotas que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Traits;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

trait RouterTrait
{
  /**
   * A URI do recurso que está sendo requisitado
   * 
   * @var string
   */
  protected $uri;

  /**
   * O método HTTP da requisição
   * 
   * @var string
   */
  protected $HTTPMethod;

  /**
   * O nome da rota requisitada.
   * 
   * @var string
   */
  protected $routeName;

  /**
   * Os métodos HTTP permitidos.
   * 
   * @var array
   */
  protected $allowedHttpMethods = [];

  /**
   * Os parâmetros passados na requisição.
   * 
   * @var array
   */
  protected $parameters;

  /**
   * Os argumentos passados na requisição.
   * 
   * @var array
   */
  protected $arguments;

  /**
   * Recupera os detalhes do endereço da requisição.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * 
   * @return void
   */
  protected function parseURI(ServerRequestInterface $request)
  {
    // Recupera os dados da URI requisitada
    $uri = $request->getUri();
    $this->uri = $uri->getPath();
    $this->HTTPMethod = $request->getMethod();
    $this->parameters = $request->getParams();
  }

  /**
   * Recupera os detalhes da rota da requisição.
   * 
   * @param  ServerRequestInterface $request A requisição
   * 
   * @return void
   */
  protected function parseRoute(ServerRequestInterface $request)
  {
    // Recupera os dados da rota requisitada
    $route = $request->getAttribute('route');
    $this->routeName = $route->getName();
    $this->allowedHttpMethods = $route->getMethods();
    $this->arguments = $route->getArguments();
  }

  /**
   * Recupera uma URL (path) pelo nome da rota
   * 
   * @param string $route
   *   O nome da rota
   * @param array $params
   *   Os parâmetros a serem passados na requisição
   * @param array $queryParams
   *   Os parâmetros a serem passados na requisição
   * 
   * @return string
   *   A rota
   */
  protected function path($route, array $params = [],
    array $queryParams = [])
  {
    return $this->container['router']
      ->pathFor($route, $params, $queryParams)
    ;
  }
  
  /**
   * Gera uma URL (path) relativa pelo nome de uma rota
   * 
   * @param string $route
   *   O nome da rota
   * @param array $params
   *   Os parâmetros a serem passados na requisição
   * @param array $queryParams
   *   Os parâmetros a serem passados na requisição
   * 
   * @return string
   *   A rota
   */
  protected function relativePath($route, array $params = [],
    array $queryParams = [])
  {
    return $this->container['router']
      ->relativePathFor($route, $params, $queryParams)
    ;
  }
  
  /**
   * Redireciona para uma rota pelo seu nome.
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param string $route
   *   O nome da rota para qual se deseja redirecionar
   * @param array $params
   *   Os parâmetros a serem enviados na requisição
   * 
   * @return Response
   *   A resposta HTTP modificada
   */
  protected function redirect(Response $response, $route,
    array $params = [])
  {
    return $response
      ->withRedirect(
          $this->container['router']->pathFor($route, $params)
        )
    ;
  }
  
  /**
   * Redireciona para uma rota pela sua URL (path).
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param string $url
   *   O endereço da rota para qual se deseja redirecionar
   * 
   * @return Response
   *   A resposta HTTP modificada
   */
  protected function redirectTo(Response $response, $url)
  {
    return $response->withRedirect($url);
  }
}
