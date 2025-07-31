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
 * Uma classe abstrata para servir como base para middlewares do
 * aplicativo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Middlewares;

use Core\Logger\LoggerTrait;
use Core\Traits\ApplicationTrait;
use Core\Traits\ContainerTrait;
use Core\Traits\RouterTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class Middleware
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * Os métodos para manipulação dos aplicativos
   */
  use ApplicationTrait;
  
  /**
   * Os métodos para manipulação das rotas
   */
  use RouterTrait;
  
  /**
   * Os tipos de conteúdos (de documentos) conhecidos
   * 
   * @var array
   */
  protected $knownContentTypes = [
    'application/json',
    'application/xml',
    'text/html',
    'text/xml'
  ];

  /**
   * O construtor do nosso middleware.
   * 
   * @param ContainerInterface $container
   *   A estrutura que contém os containers da aplicação
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    unset($container);
  }
  
  /**
   * Determina qual o tipo de conteúdo que conhecemos é desejado usando
   * o cabeçalho aceito.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * 
   * @return string
   *   O típo de conteúdo de nossa requisição
   */
  protected function determineContentType(ServerRequestInterface $request)
  {
    // Recuperamos do cabeçalho 'Accept' qual o tipo de conteúdo
    // requisitado
    $acceptHeader = $request->getHeaderLine('Accept');
    $contentTypes = explode(',', $acceptHeader);

    // Dentre os conteúdos permitidos (conhecidos), determinamos àquele
    // que corresponde ao que foi solicitado
    $selectedContentTypes =
      array_intersect($contentTypes, $this->knownContentTypes)
    ;
    if (count($selectedContentTypes)) {
      return current($selectedContentTypes);
    }
    
    // Trata +json e +xml especialmente
    if (preg_match('/\+(json|xml)/', $acceptHeader, $matches)) {
      $mediaType = 'application/' . $matches[1];
      if (in_array($mediaType, $this->knownContentTypes)) {
        return $mediaType;
      }
    }

    // Se não encontrarmos nenhum tipo válido, retornamos como HTML
    return 'text/html';
  }

  /**
   * Obtém os parâmetros contidos na matriz $params de uma requisição
   * HTTP.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param array|string $params
   *   O(s) parâmetro(s) que se deseja obter o valor
   * @param mixed $default
   *   O valor padrão a ser devolvido caso o parâmetro não esteja setado
   * na requisição
   * 
   * @return mixed
   *   Uma matriz de valores contendo o(s) parâmetro(s) e seu(s)
   * valor(es)
   */
  protected function params(ServerRequestInterface $request,
    $params, $default = null)
  {
    $data = [];

    if (is_array($params)) {
      foreach ($params as $param) {
        $data[$param] = $request->getParam($param, $default);
      }
    } else {
      $data[$params] = $request->getParam($params, $default);
    }
    
    return $data;
  }

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
  abstract public function __invoke(ServerRequestInterface $request,
    ResponseInterface $response, callable $next);
}
