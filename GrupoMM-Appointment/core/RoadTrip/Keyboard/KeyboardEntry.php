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
 * Um evento registrado à partir de um teclado usado para controle das
 * jornadas de trabalho.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\Keyboard;

use Core\RoadTrip\EventType;
use Core\RoadTrip\StopType;
use InvalidArgumentException;

final class KeyboardEntry
{
  /**
   * O nome do fabricante do teclado onde foi realizado o registro.
   * 
   * @var string
   */
  private $manufacturer;

  /**
   * A identificação do motorista que executou o comando.
   * 
   * @var integer
   */
  private $driverID = 0;

  /**
   * O tipo do evento executado.
   * 
   * @var string
   */
  private $type = EventType::UNKNOWN;

  /**
   * O tipo de parada identificada.
   * 
   * @var StopType
   */
  private $stopType = StopType::NONE;

  /**
   * Uma mensagem atribuída ao comando.
   * 
   * @var string
   */
  private $message = null;

  /**
   * O construtor do evento.
   *
   * @param string $manufacturer
   *   O nome do fabricante do teclado onde foi realizado o registro
   * @param int $driverID
   *   A identificação do motorista
   * @param int $eventType
   *   O tipo de evento
   * @param int $stopType
   *   O tipo de parada
   * @param string $message (opcional)
   *   A mensagem enviada
   */
  public function __construct(string $manufacturer, int $driverID,
    int $eventType, int $stopType, string $message = null)
  {
    $this->manufacturer = $manufacturer;
    $this->driverID     = $driverID;
    $this->type         = $eventType;

    switch ($eventType) {
      case EventType::STOP:
        // Atribuímos o tipo de parada
        $this->stopType   = $stopType;
        break;
      case EventType::MESSAGE:
        // Atribuímos a mensagem associada
        $this->message = $message;
        break;
      default:
        // Não faz nada
        break;
    }
  }

  /**
   * O método mágico que nos permite recuperar um valor de uma
   * propriedade desta classe sem permitir sua modificação.
   * 
   * @param string $property
   *   A propriedade desejada desta classe
   * 
   * @return mixed
   *
   * @throws InvalidArgumentException
   *   Em caso de ser solicitada uma propriedade inexistente
   */
  public function __get(string $property) {
    if (property_exists($this, $property)) {
      return $this->$property;
    } else {
      $class = get_class($this);
      
      throw new InvalidArgumentException("A propriedade '{$property}' "
        . "não existe em {$class}");
      
    }
  }
}
