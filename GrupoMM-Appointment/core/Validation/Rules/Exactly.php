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
 * Configuração das validações de um valor que deve ter exatamente um
 * comprimento definido, seja uma matriz ou uma cadeia de caracteres
 * (string).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class Exactly
  extends AbstractRule
{
  /**
   * O comprimento que o valor deve ter para ser considerado válido
   *
   * @var int
   */
  public $length;

  public function __construct(int $length)
  {
    $this->length = $length;
  }

  /**
   * Valida o valor de um campo de string.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    $length = $this->extractLength($input);

    return $this->validateLength($length);
  }
  
  /**
   * Permite extrair o comprimento de um valor, considerando o tamanho
   * correto mesmo em strings que contenham caracteres multibytes.
   *
   * @param mixed $input
   *   O valor para o qual desejamos obter o comprimento
   *
   * @return int
   *   O comprimento do valor
   */
  protected function extractLength($input): int
  {
    if (is_string($input)) {
      return mb_strlen($input, mb_detect_encoding($input));
    }

    if (is_array($input) || $input instanceof \Countable) {
      return count($input);
    }

    if (is_object($input)) {
      return count(get_object_vars($input));
    }

    if (is_int($input)) {
      return strlen((string)$input);
    }

    return false;
  }

  /**
   * Determina se um comprimento corresponde ao valor esperado.
   *
   * @param int $length
   *   O comprimento a ser validado
   *
   * @return bool
   */
  protected function validateLength(int $length)
  {
    return $length === $this->length;
  }
}
