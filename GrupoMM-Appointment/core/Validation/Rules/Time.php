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
 * Configuração das validações de um valor do tipo hora.
 */

/**
 * @author Henrique Moody <henriquemoody@gmail.com>
 */

namespace Core\Validation\Rules;

use Core\Validation\Helpers\CanValidateDateTime;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Exceptions\ComponentException;

class Time
  extends AbstractRule
{
  use CanValidateDateTime;

  /**
   * O formato da hora.
   * 
   * @var string
   */
  private $format;

  /**
   * @var string
   */
  private $sample;

  /**
   * Inicializa a regra de validação.
   *
   * @throws ComponentException
   */
  public function __construct(string $format = 'H:i:s')
  {
    if (!preg_match('/^[gGhHisuvaA\W]+$/', $format)) {
      throw new ComponentException(sprintf('"%s" não é um formato de "
        . "tempo válido', $format)
      );
    }

    $this->format = $format;
    $this->sample = date($format, strtotime('23:59:59'));
  }

  /**
   * Valida o valor de um campo de hora.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    if (!is_scalar($input)) {
      return false;
    }
    
    return $this->isDateTime($this->format, (string) $input);
  }
}
