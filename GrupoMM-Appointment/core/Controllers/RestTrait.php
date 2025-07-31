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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipular requisições REST que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Controllers;

use Core\Traits\RouterTrait;
use Slim\Http\Response;

trait RestTrait
{
  use RouterTrait;
  
  /**
   * Retorna uma resposta "400 Bad Request" com dados JSON
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param array $data
   *   Os dados a serem renderizados na resposta
   * 
   * @return Response
   */
  protected function badRequest(
    Response $response,
    array $data
  ): Response
  {
    return $this->json($response, $data, 400);
  }
  
  /**
   * Retorna uma resposta "201 Created" com um cabeçalho de localização
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param string $route
   *   O nome da rota
   * @param array $params
   *   Os parâmetros
   * 
   * @return Response
   */
  protected function created(
    Response $response,
    string $route,
    array $params = []
  )
  {
    return $this->redirect($response, $route, $params)
      ->withStatus(201)
    ;
  }
  
  /**
   * Escreve JSON no corpo da resposta
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param array $data
   *   Os dados a serem renderizados na resposta
   * @param integer $status
   *   O código de retorno HTTP
   * 
   * @return Response
   */
  protected function json(
    Response $response,
    array $data,
    $status = 200
  ): Response
  {
    return $response->withJson($data, $status);
  }
  
  /**
   * Retorna uma resposta "204 No Content"
   * 
   * @param Response $response
   *   A resposta HTTP
   * 
   * @return Response
   */
  protected function noContent(Response $response): Response
  {
    return $response->withStatus(204);
  }
  
  /**
   * Retorna uma resposta "200 Ok" com dados JSON
   * 
   * @param Response $response
   *   A resposta HTTP
   * @param array $data
   *   Os dados a serem renderizados na resposta
   * 
   * @return Response
   */
  protected function ok(Response $response, array $data): Response
  {
    return $this->json($response, $data);
  }
}
