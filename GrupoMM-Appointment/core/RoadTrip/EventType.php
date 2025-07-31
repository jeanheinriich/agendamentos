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
 * Classe que define um tipo de evento registrado à partir de um teclado
 * usado para registro das jornadas de trabalho.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip;

use InvalidArgumentException;
use ReflectionClass;

final class EventType
{
  /**
   * As propriedades enumeradas que relacionam os tipos de paradas
   * possíveis.
   *
   * @var Enum
   */
  public const UNKNOWN = 0;
  public const IDENTIFY = 1;
  public const MESSAGE = 2;
  public const START = 3;
  public const STOP = 4;
  public const RESTART = 5;
  public const FINISH = 6;

  protected static $names = [
    self::UNKNOWN => 'Evento desconhecido',
    self::IDENTIFY => 'Identificação do motorista',
    self::MESSAGE => 'Envio de mensagem',
    self::START => 'Início de viagem',
    self::STOP => 'Parada durante a viagem',
    self::RESTART => 'Reinício de viagem',
    self::FINISH => 'Fim de viagem'
  ];

  private function __construct()
  {
  }

  /**
   * Converte o evento para texto.
   *
   * @param int $ordinal
   *   O código do evento
   *
   * @return string
   *   O nome do evento
   *
   * @throws InvalidArgumentException
   *   Em caso do evento informado ser inválido
   */
  public static function toString(int $ordinal): string
  {
    $reflection = new ReflectionClass(get_called_class());
    $constants = $reflection->getConstants();
    $names = array_flip($constants);

    if (array_key_exists($ordinal, $names)) {
      return $names[$ordinal];
    }

    throw new InvalidArgumentException("O tipo de evento informado é "
      . "inválido"
    );
  }

  /**
   * Converte o nome do tipo de evento para um texto legível.
   *
   * @param int $ordinal
   *   O código do evento
   *
   * @return string
   *   O nome do tipo do evento de maneira legível
   * 
   * @throws InvalidArgumentException
   *   Em caso do evento informado ser inválido
   */
  public static function readable(int $ordinal): string
  {
    if (array_key_exists($ordinal, self::$names)) {
      return self::$names[$ordinal];
    }

    throw new InvalidArgumentException("O tipo de evento informado é "
      . "inválido"
    );
  }
}