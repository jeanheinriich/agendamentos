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
 * Classe responsável por verificar se um campo contém um estado (UF)
 * válido.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class OneState
  extends AbstractRule
{
  /**
   * Os valores possíveis de unidades da federação.
   *
   * @var array
   */
  public $haystack;

  /**
   * Inicializa a regra de validação.
   */
  public function __construct()
  {
    $this->haystack = [
      "AX", "AL", "AM", "AP", "BA", "CE", "DF", "ES", "GO",
      "MA", "MG", "MS", "MT", "PA", "PB", "PE", "PI", "PR",
      "RJ", "RN", "RO", "RR", "RS", "SC", "SE", "SP", "TO"
    ];
  }

  /**
   * Valida o valor de um campo de UF.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    if (is_array($this->haystack)) {
      return in_array($input, $this->haystack);
    }

    if ($input === null || $input === '') {
      return ($input == $this->haystack);
    }

    return (false !== mb_stripos($this->haystack, $input, 0,
      mb_detect_encoding($input)))
    ;
  }
}
