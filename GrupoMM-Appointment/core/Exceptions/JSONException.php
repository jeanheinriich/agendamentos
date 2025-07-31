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
 * Uma classe para erros HTTP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Exception;

class JSONException
  extends Exception
{
  /**
   * Lista dos códigos de erro JSON
   *
   * Obtido através de
   * https://www.php.net/manual/pt_BR/function.json-last-error.php
   *
   * @var array
   */
  private $status = array(
    JSON_ERROR_NONE => 'Nenhum erro',
    JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
    JSON_ERROR_STATE_MISMATCH => 'Influxo ou incompatibilidade de modos',
    JSON_ERROR_CTRL_CHAR => 'Caractere de controle inesperado encontrado',
    JSON_ERROR_SYNTAX => 'Erro de sintaxe, JSON malformado',
    JSON_ERROR_UTF8 => 'Caracteres UTF-8 malformados, possivelmente codificados incorretamente'
  );

  /**
   * A mensagem de exceção.
   * 
   * @var string
   */
  protected $statusPhrase;

  /**
   * O código de erro definido para esta exceção
   * 
   * @var integer
   */
  protected $code = 0;

  /**
   * O construtor de nossa exceção.
   * 
   * @param int $statusCode
   *   O código de estado (opcional, se nulo usará 500 como padrão)
   * @param string $statusPhrase
   *   A frase de estado (opcional, se nula usará a frase de status
   * padrão)
   */
  public function __construct($statusCode = JSON_ERROR_NONE,
    $statusPhrase = null)
  {
    if (null === $statusPhrase && isset($this->status[$statusCode])) {
      $this->statusPhrase = $this->status[$statusCode];
    } else {
      $this->statusPhrase = $statusPhrase;
    }
    $this->code = $statusCode;

    parent::__construct($this->statusPhrase, $statusCode, null);
  }

  /**
   * Converte a exceção para string.
   * 
   * @return string
   *   A mensagem de exceção
   */
  public function __toString()
  {
    return sprintf('Erro código [%d]: %s', $this->code,
      $this->statusPhrase);
  }
}