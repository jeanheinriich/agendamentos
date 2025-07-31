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
 * Configuração das validações de uma placa de veículo, incluíndo placas
 * do Mercosul.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class VehiclePlate
  extends AbstractRule
{
  /**
   * Retorna se um determinada string contém o código de uma placa de
   * veículo válida.
   *
   * @param string $input
   *   O valor a ser validado
   *
   * @return bool
   */
  private function verifyValidPlate($input)
  {
    $pattern = '/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/';

    return preg_match($pattern, $input);
  }

  /**
   * Valida o valor de um campo de placa.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    if (empty($input)) {
      return false;
    }
    
    return $this->verifyValidPlate($input);
  }
}
