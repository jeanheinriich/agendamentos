<?php
/*
 * This file is part of the payment's API library.
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
 * A classe que prove as moedas disponíveis para emissão de um boleto.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments;

use InvalidArgumentException;

final class Coins
{
  /**
   * As propriedades enumeradas que relacionam as moedas disponíveis
   * para uso em um boleto
   *
   * @var Enum
   */
  const CURRENCY_OTHER = 0;
  const CURRENCY_BRAZILIAN_REAL = 9;

  /**
   * Dados das moedas
   *
   * @var array
   */
  protected static $species = [
    self::CURRENCY_OTHER => [
      'name'   => 'OUTRA',
      'symbol' => ''
    ],
    self::CURRENCY_BRAZILIAN_REAL => [
      'name'   => 'REAL',
      'symbol' => 'R$'
    ]
  ];

  private function __construct()
  {
  }

  /**
   * Obtém os dados da moeda.
   *
   * @param int $ordinal
   *   O código da moeda
   *
   * @return array
   *   Os dados da moeda
   *
   * @throws InvalidArgumentException
   *   Em caso da moeda informada ser inválida
   */
  public static function getSpecie(int $ordinal): array
  {
    if (array_key_exists($ordinal, self::$species)) {
      return self::$species[$ordinal];
    }

    throw new InvalidArgumentException("A moeda informada é "
      . "inválida"
    );
  }
}
