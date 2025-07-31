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
 * Um formatador de mensagens de erro no formato de uma stream de
 * callback.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers\Formatters;

use Throwable;

class StreamFormatter
  extends ErrorFormatter
{
  /**
   * Renderiza um erro.
   * 
   * @param Throwable $error
   *   A exceção/erro que contém a mensagem
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   A mensagem de erro formatada
   */
  public function renderError(
    Throwable $error,
    array $params
  ): string
  {
    // Devolve uma mensagem de erro em JSON para um callback como
    // resultado
    $json = [
      'result' => 'ERROR',
      'count' => 0,
      'total' => 0,
      'message' => $this->getErrorDescription($error),
      'data' => [],
      'exception' => []
    ];
    
    if ($this->displayErrorDetails) {
      $json['exception'] = [];

      do {
        $json['exception'][] = [
          'type' => get_class($error),
          'code' => $error->getCode(),
          'message' => $error->getMessage(),
          'file' => $error->getFile(),
          'line' => $error->getLine(),
          'trace' => explode("\n", $error->getTraceAsString())
        ];
      } while ($error = $error->getPrevious());
    }

    return "data: " . json_encode($json, JSON_PRETTY_PRINT);
  }

  /**
   * Renderiza uma mensagem de erro.
   * 
   * @param string $error
   *   A mensagem de erro
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   Os dados formatados
   */
  public function renderErrorMessage(
    string $error,
    array $params
  ): string
  {
    // Devolve uma mensagem de erro em JSON para um callback como
    // resultado
    $json = [
      'result' => 'ERROR',
      'count' => 0,
      'total' => 0,
      'message' => $error,
      'data' => [],
      'exception' => []
    ];

    return "data: " . json_encode($json, JSON_PRETTY_PRINT);
  }
}