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
 * A classe que prove as possíveis instruções aplicáveis a um boleto e
 * que são enviadas no arquivo de remessa.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

use InvalidArgumentException;
use ReflectionClass;

final class BilletInstruction
{
  /**
   * As propriedades enumeradas que relacionam as instruções possíveis
   * para um boleto
   *
   * @var Enum
   */
  public const REGISTRATION = 1;
  public const DISCHARGE = 2;
  public const DISCOUNT = 3;
  public const DISCOUNT_CANCEL = 4;
  public const DATE_CHANGE = 5;
  public const MODIFICATION = 6;
  public const REQUEST_PROTEST = 7;
  public const SUSPEND_PROTEST_AND_DISCHARGE = 8;
  public const SUSPEND_PROTEST_AND_REMAIN_PENDING = 9;
  public const REQUEST_CREDIT_BLOCKED = 10;
  public const SUSPEND_CREDIT_BLOCKED_AND_DISCHARGE = 11;
  public const SUSPEND_CREDIT_BLOCKED_AND_REMAIN_PENDING = 12;
  public const CUSTOM = 13;

  /**
   * As descrições das instruções
   *
   * @var array
   */
  protected static $names = [
    self::REGISTRATION => 'Registro',
    self::DISCHARGE => 'Baixa',
    self::DISCOUNT => 'Abatimento',
    self::DISCOUNT_CANCEL => 'Cancelamento do abatimento',
    self::DATE_CHANGE => 'Alteração do vencimento',
    self::MODIFICATION => 'Alteração de outros dados',
    self::REQUEST_PROTEST => 'Pedido de protesto',
    self::SUSPEND_PROTEST_AND_DISCHARGE => 'Sustar protesto e baixar o título',
    self::SUSPEND_PROTEST_AND_REMAIN_PENDING => 'Sustar protesto e manter pendente em carteira',
    self::REQUEST_CREDIT_BLOCKED => 'Pedido de negativação',
    self::SUSPEND_CREDIT_BLOCKED_AND_DISCHARGE => 'Sustar negativação e baixar o título',
    self::SUSPEND_CREDIT_BLOCKED_AND_REMAIN_PENDING => 'Sustar negativação e manter pendente em carteira',
    self::CUSTOM => 'Customizado'
  ];

  /**
   * O construtor de nossa classe.
   */
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
   *   A descrição da instrução
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
}
