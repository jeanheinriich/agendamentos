<?php
/*
 * This file is part of the road trip registre analysis library.
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
 * Classe que define as condições em que uma viagem rodoviária pode se
 * encontrar em relação às informações que contém, como início e término
 * de viagem, bem como as informações de paradas (se existirem).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip;
use InvalidArgumentException;

final class RoadTripStatus
{
  /**
   * A viagem encontra-se em um estado desconhecido. Isto pode ocorrer
   * durante o processamento antes de que a viagem tenha seu estado
   * inicial atribuído.
   * 
   * @var int
   */
  public const UNKNOWN = 0;

  /**
   * A viagem está incompleta, e pode não possuir as informações de
   * início e/ou término.
   * 
   * @var int
   */
  public const INCOMPLETE = 1;

  /**
   * A viagem está parcialmente completa pois, apesar de conter as
   * informações de início e término, algumas paradas não estão
   * corretamente descritas.
   * 
   * @var int
   */
  public const COMPLETED_PARTIALLY = 2;

  /**
   * A viagem está completa, e todas as suas paradas foram corretamente
   * descritas.
   * 
   * @var int
   */
  public const COMPLETED_SUCCESSFULLY = 3;

  protected static $names = [
    self::UNKNOWN                => 'Desconhecida',
    self::INCOMPLETE             => 'Incompleta',
    self::COMPLETED_PARTIALLY    => 'Parcialmente completa',
    self::COMPLETED_SUCCESSFULLY => 'Completa'
  ];

  private function __construct()
  {
  }

  /**
   * Converte a condição da viagem para texto.
   *
   * @param int $ordinal
   *   O código da condição
   *
   * @return array
   *   O nome da condição
   *
   * @throws InvalidArgumentException
   *   Em caso da condição informada ser inválida
   */
  public static function toString(int $ordinal): string
  {
    if (array_key_exists($ordinal, self::$names)) {
      return self::$names[$ordinal];
    }

    throw new InvalidArgumentException("A condição informada é "
      . "inválida"
    );
  }
}