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
 * lidar com questões relacionadas com veículos que outras classes podem
 * incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

trait VehicleTrait
{
  /**
   * Realiza a comparação entre duas placas ignorando a variação entre
   * uma placa no padrão Mercosul e o padrão antigo, permitindo
   * identificar um veículo independente da maneira como foi cadastrado.
   * 
   * @param string $plate1
   *   A placa de referência
   * @param string $plate2
   *   A placa a ser comparada
   *
   * @return bool
   *   Se as placas são do mesmo veículo
   */
  protected function isSamePlate(
    string $plate1,
    string $plate2
  ): bool
  {
    $plate1 = strtoupper($plate1);
    $plate2 = strtoupper($plate1);

    if ( (strlen($plate1) < 7) || (strlen($plate1) < 7) ) {
      return false;
    }

    $plate1 = $this->toMercosurFormat($plate1);
    $plate2 = $this->toMercosurFormat($plate2);

    if ( $this->isValidPlate($plate1) && $this->isValidPlate($plate2) ) {
      return (strcmp($plate1, $plate2) == 0);
    }

    return false;
  }

  /**
   * Retorna se um determinada string contém o código de uma placa de
   * veículo válida.
   *
   * @param string $input
   *   O valor a ser validado
   *
   * @return bool
   */
  private function isValidPlate($input)
  {
    $pattern = '/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/';

    return preg_match($pattern, $input);
  }

  /**
   * Converte uma placa para o formato Mercosul.
   *
   * @param string $plate
   *   A placa a ser convertida
   *
   * @return string
   */
  private function toMercosurFormat(string $plate): string
  {
    // Sempre pegamos apenas os 7 primeiros dígitos
    $plate = substr($plate, 0, 7);

    // Analisamos o 5º caracter
    $fiveChar = substr($plate, 4, 1);

    switch ($fiveChar) {
      case '0': $newChar = 'A'; break;
      case '1': $newChar = 'B'; break;
      case '2': $newChar = 'C'; break;
      case '3': $newChar = 'D'; break;
      case '4': $newChar = 'E'; break;
      case '5': $newChar = 'F'; break;
      case '6': $newChar = 'G'; break;
      case '7': $newChar = 'H'; break;
      case '8': $newChar = 'I'; break;
      case '9': $newChar = 'J'; break;
      default:
        // Não modifica
        $newChar = $fiveChar;

        break;
    }

    // Retornamos a placa
    return substr($plate, 0, 4) . $newChar . substr($plate, 5, 2);
  }
}
