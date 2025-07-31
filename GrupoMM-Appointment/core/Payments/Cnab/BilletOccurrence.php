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
 * A classe que prove as possíveis ocorrência em uma transação de um
 * boleto.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

use InvalidArgumentException;
use ReflectionClass;

final class BilletOccurrence
{
  /**
   * As propriedades enumeradas que relacionam as ocorrências possíveis
   * que uma transação de um boleto pode gerar
   *
   * @var Enum
   */
  public const LIQUIDATED = 1;
  public const DROPPED = 2;
  public const ENTRY = 3;
  public const CHANGE = 4;
  public const PROTESTED = 5;
  public const UNPROTESTED = 6;
  public const CREDIT_BLOCKED = 7;
  public const CREDIT_UNBLOCKED = 8;
  public const OTHERS = 9;
  public const TARIFF = 10;
  public const ABATEMENT = 11;
  public const UNABATEMENT = 12;
  public const ERROR = 13;

  /**
   * As descrições dos estados
   *
   * @var array
   */
  protected static $names = [
    self::LIQUIDATED => 'Liquidado',
    self::DROPPED => 'Baixado',
    self::ENTRY => 'Registrado',
    self::CHANGE => 'Modificado',
    self::PROTESTED => 'Protestado',
    self::UNPROTESTED => 'Exclusão de protesto',
    self::CREDIT_BLOCKED => 'Negativado',
    self::CREDIT_UNBLOCKED => 'Exclusão de negativação',
    self::OTHERS => 'Outros motivos',
    self::TARIFF => 'Débito de tarifas/custas',
    self::ABATEMENT => 'Abatimento do valor',
    self::UNABATEMENT => 'Retirada do abatimento do valor',
    self::ERROR => 'Erro'
  ];

  /**
   * O construtor de nossa classe.
   */
  private function __construct()
  {
  }

  /**
   * Converte a ocorrência para texto.
   *
   * @param int $ordinal
   *   O código da ocorrência
   *
   * @return string
   *   A descrição da ocorrência
   *
   * @throws InvalidArgumentException
   *   Em caso da ocorrência informada ser inválida
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

    throw new InvalidArgumentException("A ocorrência informada é inválido");
  }
}
