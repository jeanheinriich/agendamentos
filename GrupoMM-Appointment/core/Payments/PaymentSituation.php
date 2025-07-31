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
 * A classe que prove as possíveis situações aplicáveis a uma cobrança e
 * que são registradas no banco de dados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments;

use InvalidArgumentException;
use ReflectionClass;

final class PaymentSituation
{
  /**
   * As propriedades enumeradas que relacionam os estados possíveis em
   * que uma cobrança pode estar
   *
   * @var Enum
   */
  public const RECEIVABLE = 1;
  public const PAIDED = 2;
  public const CANCELED = 3;
  public const NEGOTIATED = 4;
  public const RENEGOTIATED = 5;

  /**
   * As descrições dos estados
   *
   * @var array
   */
  protected static $names = [
    self::RECEIVABLE => 'A receber',
    self::PAIDED => 'Pago',
    self::CANCELED => 'Cancelado',
    self::NEGOTIATED => 'Negociado',
    self::RENEGOTIATED => 'Renegociado'
  ];

  /**
   * O construtor de nossa classe.
   */
  private function __construct()
  {
  }

  /**
   * Converte a situação para texto.
   *
   * @param int $ordinal
   *   O código da situação
   *
   * @return string
   *   A descrição da situação
   *
   * @throws InvalidArgumentException
   *   Em caso da situação informada ser inválida
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

    throw new InvalidArgumentException("A situação informado é "
      . "inválida"
    );
  }
}
