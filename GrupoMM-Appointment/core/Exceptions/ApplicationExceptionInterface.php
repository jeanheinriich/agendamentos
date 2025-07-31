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
 * Uma interface para classes de exceção provocadas por erro HTTP na
 * aplicação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ApplicationExceptionInterface
{
  /**
   * Criar um nova exceção.
   * 
   * @param ServerRequestInterface $request
   *   O objeto da requisição
   * @param ResponseInterface $response
   *   O objeto da resposta
   * @param Exception|null $previous
   *   A exceção anterior (caso exista)
   */
  public function __construct(ServerRequestInterface $request,
    ResponseInterface $response, string $message = null);

  /**
   * Converte a exceção para string.
   * 
   * @return string
   *   A exceção em forma de texto
   */
  public function __toString();
  
  /**
   * Recupera a mensagem de exceção.
   * 
   * @return string
   *   A mensagem de exceção
   */
  public function getMessage();

  /**
   * Recupera o código de erro da exceção.
   * 
   * @return int
   *   O código de erro
   */
  public function getCode();

  /**
   * Métodos protegidos herdados da classe SlimException
   */
  
  /**
   * Obtém a requisição HTTP.
   *
   * @return ServerRequestInterface
   *   A requisição HTTP
   */
  public function getRequest();

  /**
   * Obtém a resposta HTTP.
   *
   * @return ResponseInterface
   *   A resposta HTTP
   */
  public function getResponse();
}