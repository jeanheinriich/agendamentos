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
 * A classe que provê a instrução a ser enviado ao banco para adotar em
 * caso de vencimento do título.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

use InvalidArgumentException;
use ReflectionClass;

final class Instructions
{
  /**
   * As propriedades enumeradas que relacionam as possíveis instruções
   * que o banco deve adotar após o vencimento do título
   *
   * @var Enum
   */
  const NONE = 0;
  const NOT_RECEIVE_AFTER_EXPIRATION = 1;
  const NOT_CHARGE_FINE_VALUE = 2;
  const PROTEST = 3;
  const BANKRUPTCY_PROTEST = 4;
  const NEGATIVATE = 5;
  const DROP_BECAUSE_EXPIRY_OF_TERM = 6;
  const CANCEL_PROTEST_OR_NEGATIVATION = 99;

  protected static $names = [
    self::NONE => 'Não utilizar',
    self::NOT_RECEIVE_AFTER_EXPIRATION => 'Não receber após o vencimento',
    self::NOT_CHARGE_FINE_VALUE => 'Não cobrar juros de mora',
    self::PROTEST => 'Protestar',
    self::BANKRUPTCY_PROTEST => 'Protesto Falimentar',
    self::NEGATIVATE => 'Negativar',
    self::DROP_BECAUSE_EXPIRY_OF_TERM => 'Baixa por decurso de prazo',
    self::CANCEL_PROTEST_OR_NEGATIVATION => 'Cancelar protesto/negativação'
  ];

  private function __construct()
  {
  }

  /**
   * Converte a instrução para texto.
   *
   * @param int $ordinal
   *   O código da instrução
   *
   * @return string
   *   O nome da instrução
   *
   * @throws InvalidArgumentException
   *   Em caso da instrução informada ser inválida
   */
  public static function toString(int $ordinal): string
  {
    $reflection = new ReflectionClass(get_called_class());
    $constants = $reflection->getConstants();
    $constantNames = array_flip($constants);

    if (array_key_exists($ordinal, self::$names)) {
      return self::$names[$ordinal];
    }

    if (array_key_exists($ordinal, $constantNames)) {
      return $constantNames[$ordinal];
    }

    throw new InvalidArgumentException("A instrução informada é "
      . "inválida"
    );
  }

  /**
   * Determina se a instrução é válida.
   *
   * @param int $ordinal
   *
   * @return bool
   */
  public static function isValid(int $ordinal): bool
  {
    return array_key_exists($ordinal, self::$names);
  }
}
