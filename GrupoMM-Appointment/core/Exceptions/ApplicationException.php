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
 * Uma classe de exceção provocadas por erro HTTP na aplicação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApplicationException
  extends Exception
  implements ApplicationExceptionInterface
{
  /**
   * O objeto contendo à requisição HTTP.
   *
   * @var ServerRequestInterface
   */
  protected $request;

  /**
   * O objeto contendo à resposta HTTP para ser enviada ao cliente.
   *
   * @var ResponseInterface
   */
  protected $response;

  /**
   * A mensagem de exceção.
   * @var string
   */
  protected $message = 'Erro HTTP';

  /**
   * O código de erro definido para esta exceção.
   * 
   * @var integer
   */
  protected $code = 0;

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
    ResponseInterface $response, string $message = null)
  {
    if (!$message) {
      $message = $this->message;
    }

    parent::__construct($message, $this->code, null);

    // Seta os erros internamente
    $this->request  = $request;
    $this->response = $response;
    $this->message  = $message;
  }

  /**
   * Converte a exceção para string.
   * 
   * @return string
   *   A exceção em forma de texto
   */
  public function __toString()
  {
    return get_class($this)
      . " Erro código [{$this->code}]: '{$this->message}'"
    ;
  }

  /**
   * Obtém a requisição HTTP.
   *
   * @return ServerRequestInterface
   *   A requisição HTTP
   */
  public function getRequest()
  {
    return $this->request;
  }

  /**
   * Obtém a resposta HTTP.
   *
   * @return ResponseInterface
   *   A resposta HTTP
   */
  public function getResponse()
  {
      return $this->response;
  }

  /**
   * Define uma nova resposta HTTP.
   * 
   * @param ResponseInterface $response
   *   A resposta HTTP
   */
  public function setResponse(ResponseInterface $response): void
  {
    $this->response = $response;
  }
}