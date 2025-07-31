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
 * A classe que prove as possíveis restrições aplicáveis a uma cobrança
 * e que são registradas no banco de dados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments;

use InvalidArgumentException;
use ReflectionClass;

final class PaymentRestriction
{
  /**
   * As propriedades enumeradas que relacionam as restrições aplicadas
   * em uma cobrança
   *
   * @var Enum
   */
  public const NONE = 0;
  public const PROTESTED = 1;
  public const CREDIT_BLOCKED = 2;
  public const SENT_TO_DUNNING_AGENCY = 4;

  /**
   * Determina se o título está protestado.
   * 
   * @param int $restrictions
   *   As restrições aplicadas
   *
   * @return boolean
   */
  public static function isProtested(int $restrictions): bool
  {
    return (($restrictions & self::PROTESTED) == self::PROTESTED);
  }

  /**
   * Determina se o título está negativado.
   * 
   * @param int $restrictions
   *   As restrições aplicadas
   *
   * @return boolean
   */
  public static function isCreditBlocked(int $restrictions): bool
  {
    return (($restrictions & self::CREDIT_BLOCKED) == self::CREDIT_BLOCKED);
  }

  /**
   * Determina se o título foi enviado para uma agência de cobrança.
   * 
   * @param int $restrictions
   *   As restrições aplicadas
   *
   * @return boolean
   */
  public static function isSentToDunningAgency(int $restrictions): bool
  {
    return (($restrictions & self::SENT_TO_DUNNING_AGENCY) == self::SENT_TO_DUNNING_AGENCY);
  }

  /**
   * As descrições dos estados
   *
   * @var array
   */
  protected static $names = [
    self::NONE => 'Sem restrições',
    self::PROTESTED => 'Protestado',
    self::CREDIT_BLOCKED => 'Negativado',
    self::PROTESTED | self::CREDIT_BLOCKED => 'Protestado e negativado',
    self::SENT_TO_DUNNING_AGENCY => 'Enviado para agência de cobrança',
    self::PROTESTED | self::SENT_TO_DUNNING_AGENCY => 'Protestado e enviado para agência de cobrança',
    self::CREDIT_BLOCKED | self::SENT_TO_DUNNING_AGENCY => 'Negativado e enviado para agência de cobrança',
    self::PROTESTED | self::CREDIT_BLOCKED | self::SENT_TO_DUNNING_AGENCY => 'Protestado, negativado e enviado para agência de cobrança'
  ];

  /**
   * O construtor de nossa classe.
   */
  private function __construct()
  {
  }

  /**
   * Converte a restrição para texto.
   *
   * @param int $ordinal
   *   O código da restrição
   *
   * @return string
   *   A descrição da restrição
   *
   * @throws InvalidArgumentException
   *   Em caso da restrição informada ser inválida
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

    throw new InvalidArgumentException("A restrição informado é "
      . "inválida"
    );
  }
}
