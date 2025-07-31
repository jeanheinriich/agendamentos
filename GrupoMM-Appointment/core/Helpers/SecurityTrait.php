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
 * lidar com questões de segurança em uma requisição que outras classes
 * podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

use Core\Exceptions\AccessDeniedException;
use Core\Exceptions\UnauthorizedException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

trait SecurityTrait
{
  /**
   * Adiciona uma mensagem de erro.
   * 
   * @param string $message
   *   A mensagem para logs
   * @param string $humanizedMessage
   *   A mensagem para exibição em tela
   */
  protected function addError(
    string $message,
    string $humanizedMessage
  ): void
  {
    $this->error = [
      'message' => $message,
      'humanizedMessage' => $humanizedMessage
    ];
  }

  /**
   * Recupera uma mensagem de erro.
   * 
   * @return array
   *   Uma matriz contendo a mensagem de erro para logs e a mensagem de
   *   erro para exibição em tela
   */
  protected function getError(): array
  {
    return $this->error;
  }
    
  /**
   * Cria uma nova exceção de acesso negado (AccessDeniedException).
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param string $message
   *   A mensagem de erro
   * 
   * @throws AccessDeniedException
   */
  protected function accessDeniedException(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $message = 'Acesso negado'
  ): void
  {
    throw new AccessDeniedException($request, $response, $message);
  }
  
  /**
   * Cria uma nova exceção de acesso não autorizado (UnauthorizedException)
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param string $message
   *   A mensagem de erro
   * 
   * @throws UnauthorizedException
   */
  protected function unauthorizedException(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $message = 'Não autorizado'
  ): void
  {
    throw new UnauthorizedException($request, $response, $message);
  }
}
