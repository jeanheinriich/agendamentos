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

namespace Core\RoadTrip;

use Carbon\Carbon;
use Core\RoadTrip\Keyboard\KeyboardEntry;
use InvalidArgumentException;

final class EventEntry
{
  /**
   * O número de identificação do registro.
   * 
   * @var integer
   */
  private $registreID = 0;

  /**
   * A data/hora do evento
   * 
   * @var Carbon
   */
  private $eventDate;

  /**
   * A data/hora do final do evento
   * 
   * @var Carbon
   */
  private $endDate;

  /**
   * O local onde o evento ocorreu
   * 
   * @var string
   */
  private $location = null;

  /**
   * O tipo do evento executado.
   * 
   * @var EventType
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
   * A flag indicativa de que este evento deve ser ignorado
   * 
   * @var bool
   */
  private $ignore = false;

  /**
   * A flag indicativa de que este evento foi corrigido automaticamente
   * 
   * @var bool
   */
  private $autoFixed = false;

  /**
   * A flag indicativa de que este evento está incompleto
   * 
   * @var bool
   */
  private $incomplete = false;

  /**
   * A duração (em segundos) deste evento.
   * 
   * @var int
   */
  private $duration = 0;

  /**
   * O construtor do evento.
   *
   * @param int $registreID
   *   O número de identificação do registro
   * @param int $driverID
   *   A identificação do motorista responsável no momento do evento
   * @param string $plate
   *   A placa do veículo
   * @param Carbon $eventDate
   *   A data/hora do evento
   * @param string $location
   *   A localização onde este evento ocorreu
   * @param KeyboardEntry $event
   *   O evento recebido do teclado
   */
  public function __construct(int $registreID, Carbon $eventDate,
    string $location, KeyboardEntry $event)
  {
    $this->registreID = $registreID;
    $this->eventDate  = $eventDate;
    $this->type       = $event->type;
    $this->location   = $location;

    switch ($this->type) {
      case EventType::STOP:
        // Atribuímos o tipo de parada
        $this->stopType   = $event->stopType;
        break;
      case EventType::MESSAGE:
        // Atribuímos a mensagem associada
        $this->message = $event->message;
        break;
      default:
        // Não faz nada
        break;
    }
  }

  /**
   * Define que este evento deve ser ignorado.
   */
  public function setToIgnore(): void
  {
    $this->ignore = true;
  }

  /**
   * Define que este evento está incompleto.
   */
  public function setIncomplete(): void
  {
    $this->incomplete = true;
  }

  /**
   * Modifica o tipo do evento.
   *
   * @param int $eventType
   *   O novo tipo de evento a ser atribuído
   */
  public function setType(int $eventType): void
  {
    $this->type = $eventType;
    $this->autoFixed = true;
  }

  /**
   * Permite calcular a duração deste evento (em segundos) em relação à
   * data/hora informada (normalmente a data/hora do próximo evento).
   *
   * @param Carbon $nextEventDate
   *   A data/hora em relação à qual será calculada a duração
   */
  public function calculateDurationUntil(Carbon $nextEventDate)
  {
    $this->endDate = $nextEventDate;
    $this->duration = $this->eventDate->diffInSeconds($nextEventDate);
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

  /**
   * Converte o evento para uma matriz
   *
   * @return array
   */
  public function toArray(): array
  {
    return [
      'registreID'  => $this->registreID,
      'ignore'      => $this->ignore,
      'autofixed'   => $this->autoFixed,
      'incomplete'  => $this->incomplete,
      'datetime'    => $this->eventDate,
      'duration'    => $this->duration,
      'endtime'     => ($this->type === EventType::FINISH)
        ? $this->eventDate
        : $this->endDate,
      'eventType'   => $this->type,
      'stopType'    => $this->stopType,
      'message'     => $this->message,
      'location'    => $this->location
    ];
  }
}