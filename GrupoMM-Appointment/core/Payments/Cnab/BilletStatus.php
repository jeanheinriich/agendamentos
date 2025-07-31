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
 * A classe que prove os possíveis estados aplicáveis a um boleto e que
 * são registradas no banco de dados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

use InvalidArgumentException;
use ReflectionClass;

final class BilletStatus
{
  /**
   * As propriedades enumeradas que relacionam os estados possíveis que
   * um boleto pode estar
   *
   * @var Enum
   */
  public const NOT_REGISTERED = 1;
  public const REGISTERED = 2;
  public const LIQUIDATED = 3;
  public const DROPPED_BECAUSE_LAPSE_OF_TERM = 4;
  public const MANUALLY_DROPPED = 5;

  /**
   * As descrições dos estados
   *
   * @var array
   */
  protected static $names = [
    self::NOT_REGISTERED => 'Não registrado',
    self::REGISTERED => 'Em aberto',
    self::LIQUIDATED => 'Compensado',
    self::DROPPED_BECAUSE_LAPSE_OF_TERM => 'Baixado por decurso de prazo',
    self::MANUALLY_DROPPED => 'Baixado manualmente'
  ];

  /**
   * O construtor de nossa classe.
   */
  private function __construct()
  {
  }

  /**
   * Converte o estado para texto.
   *
   * @param int $ordinal
   *   O código do estado
   *
   * @return string
   *   A descrição do estado
   *
   * @throws InvalidArgumentException
   *   Em caso do estado informada ser inválido
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

    throw new InvalidArgumentException("O estado informado é inválido");
  }
}
