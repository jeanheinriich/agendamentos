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
 * lidar com exceções e erros em uma requisição que outras classes
 * podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Traits;

use Core\Exceptions\AccessDeniedException;
use Core\Exceptions\UnauthorizedException;
use DateTime;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use InvalidArgumentException;
use OutOfBoundsException;
use Slim\Exception\NotFoundException;

trait ExceptionTrait
{
  /**
   * As mensagens de erro retornadas por processos internos
   * 
   * @var array
   */
  protected $error = [];

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
   * erro para exibição em tela
   */
  protected function getError(): array
  {
    return $this->error;
  }
    
  /**
   * Cria uma nova exceção de acesso negado
   * ({@link AccessDeniedException}).
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
   * Cria uma exceção de "não encontrado" ({@link NotFoundException}).
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * 
   * @throws NotFoundException
   */
  protected function notFoundException(
    ServerRequestInterface $request,
    ResponseInterface $response
  ): void
  {
    throw new NotFoundException($request, $response);
  }
  
  /**
   * Cria uma nova exceção de erro de execução
   * ({@link RuntimeException}).
   * 
   * @param string $message
   *   A mensagem de erro de execução
   * 
   * @throws RuntimeException
   */
  protected function runtimeException(
    string $message
  ): void
  {
    throw new RuntimeException($message);
  }
  
  /**
   * Cria uma nova exceção de acesso não autorizado
   * ({@link UnauthorizedException}).
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

  /**
   * Cria uma nova exceção de argumento inválido
   * ({@link InvalidArgumentException}) com um backtrace que faz
   * referência ao chamador real do código do usuário.
   *
   * @param array $args
   *   Os argumentos da função a serem serializados na mensagem de
   *   exceção, de forma a facilitar a interpretação do erro
   * @param string $label
   *   O prefíxo da mensagem
   * @param int $limit
   *   Limite de rastreamento de pilha
   * 
   * @throws InvalidArgumentException
   */
  protected static function invalidArgumentException(
    ?array $args = null,
    string $label = 'Argumento inválido fornecido',
    int $limit = 2
  ): void
  {
    // Obtemos os registos de chamadas (rastreamento) para nos permitir
    // identificar o objeto onde ocorreu a chamada
    $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
    $traces = end($traces);

    if ($traces) {
      // Temos os registros de chamadas, então montamos a exceção
      throw new InvalidArgumentException(
        sprintf("%s na função %s%s%s(%s) no arquivo %s na linha %s",
          $label,
          $traces['class'],
          $traces['type'],
          $traces['function'],
          self::serializeArguments($args),
          $traces['file'],
          $traces['line']
        )
      );
    }

    throw new InvalidArgumentException($label);
  }

  /**
   * Cria uma nova exceção de valor fora dos limites
   * ({@link OutOfBoundsException}) com um registo de chamadas
   * que faz referência ao chamador real do código.
   *
   * @param $value
   *   O valor cujos limites foram excedidos
   * @param string $message
   *   A mensagem de erro
   * 
   * @throws OutOfBoundsException
   */
  protected static function outOfBoundsException(
    $value,
    $message = 'Valor inválido'
  ): void
  {
    // Obtemos os registos de chamadas (rastreamento) para nos permitir
    // identificar o objeto onde ocorreu a chamada
    $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $traces = end($traces);

    if ($traces) {
      // Temos os registros de chamadas, então montamos a exceção
      throw new OutOfBoundsException(
        sprintf("%s para %s%s%s(%s) no arquivo %s na linha %s",
        $message,
        $traces['class'],
        $traces['type'],
        $traces['function'],
        self::serializeArguments([$value]),
        $traces['file'],
        $traces['line']
      ));
    }

    throw new OutOfBoundsException($message);
  }

  /**
   * Serializa os argumentos passados.
   *
   * @param array $args
   *   Os argumentos
   * @param integer $limit
   *   O limite de serialização
   *
   * @return string
   */
  protected static function serializeArguments(
    array $args,
    $limit = 32
  ): string
  {
    $list = [];

    if ($args) {
      $i = 0;

      // Percorremos todos os argumentos, interpretando cada valor
      // corretamente
      foreach ($args as $arg) {
        $i++;
        switch (true) {
          case $arg === null:
            // Valor nulo
            $list[] = "NULL";

            break;
          case is_numeric($arg):
            // Valor numérico
            $list[] = $arg;

            break;
          case is_object($arg) && method_exists($arg, '__toString'):
          case is_string($arg):
            // Texto
            $list[] = '"' . (strlen($arg) > $limit ? substr($arg, 0, $limit) . '...' : $arg) . '"';

            break;
          case $arg instanceof DateTime:
            // Data/hora
            $list[] = sprintf('"%s"', $arg->format(DATE_RFC3339));

            break;
          case is_callable($arg):
            // Método chamável
            $list[] = '{callable}';

            break;
          case $arg !== null && is_resource($arg):
            // Recurso
            $list[] = '{' . get_resource_type($arg) . '}';

            break;
          default:
            $list[] = "{arg$i}";
        }
      }
    }
    
    return $list ? implode(', ', $list) : '';
  }
}
