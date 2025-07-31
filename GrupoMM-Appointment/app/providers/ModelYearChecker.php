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
 * Classe responsável pela verificação do ano do modelo do veículo em
 * função do número do chassi.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * @TODO: Pode estar com erros. Verificar
 */

namespace App\Providers;

class ModelYearChecker
{
  /**
   * A matriz com as informações de conversão das letras para os
   * respectivos anos de fabricação.
   *
   * @const  array
   */
  const letterToYearBase = [
    'A' => '1980',
    'B' => '1981',
    'C' => '1982',
    'D' => '1983',
    'E' => '1984',
    'F' => '1985',
    'G' => '1986',
    'H' => '1987',
    'J' => '1988',
    'K' => '1989',
    'L' => '1990',
    'M' => '1991',
    'N' => '1992',
    'P' => '1993',
    'R' => '1994',
    'S' => '1995',
    'T' => '1996',
    'V' => '1997',
    'W' => '1998',
    'X' => '1999',
    'Y' => '2000',
    '1' => '2001',
    '2' => '2002',
    '3' => '2003',
    '4' => '2004',
    '5' => '2005',
    '6' => '2006',
    '7' => '2007',
    '8' => '2008',
    '9' => '2009'
  ];

  /**
   * Verifica se o VIN informado é válido.
   * 
   * @param string $vin
   *   O código VIN a ser avaliado
   * @param string $yearfabr
   *   O ano de fabricação
   * @param string $yearmodel
   *   O ano do modelo
   * 
   * @return bool
   *   O resultado da operação
   */
  public static function verify(string $vin, string $yearfabr,
    string $yearmodel) {
    // Se o VIN estiver em branco, ignora a verificação
    if (empty($vin)) {
      return true;
    }

    // Verifica o ano de fabricação em relação ao ano do modelo
    $fab = intval($yearfabr);
    $mod = intval($yearmodel);
    if (($fab > $mod) || (($mod - $fab) > 1)) {
      // Se a diferença entre o ano de fabricação e o ano de modelo for maior do
      // que 1 ou o ano de fabricação for maior do que o ano do modelo, então
      // retorna falso
      return false;
    }

    // Recupera a letra correspondente à informação do ano do código VIN
    $yearLetter = substr($vin, 9, 1);

    // Com base na letra recuperamos o ano base. As letras e números permitem
    // uma combinação de 30 anos, quando se repetem. Desta forma, a partir do
    // ano base, precisamos prosseguir as verificações até o ano corrente
    $yearBase   = self::letterToYearBase[$yearLetter];
    $maxYear    = intval(date("Y")) + 1;

    do {
      // Verifica se o ano corresponde ao informado no modelo
      if ($yearBase == $mod)
        return true;

      // Incrementa a base de comparação em 30 anos
      $yearBase = $yearBase + 30;
    } while ($yearBase <= $maxYear);

    return false;
  }
}
