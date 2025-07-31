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
 * Um adaptador que nos permite interpretar os comandos oriundos de
 * teclados fabricados pela SGBRAS e que são usados para registros das
 * jornadas de trabalho e que estão acoplados ao equipamento de
 * rastreamento. Os dados são transmitidos através de RS232 e enviados
 * junto com os dados de posicionamento do veículo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\Keyboard;

use Core\RoadTrip\EventType;
use Core\RoadTrip\StopType;

class SGBRAS
  implements KeyboardAdapter
{
  /**
   * A função que nos permite interpretar os dados dos comandos oriundos
   * do teclado, convertendo-os para um objeto comando. Separa as partes
   * do comando, devolvendo em forma de um objeto o conteúdo do comando.
   * 
   * @param string $rs232Data
   *   Os dados recebidos do rastreador provenientes da interface RS232
   *   que estabelece comunicação com o teclado nele acoplado
   * 
   * @return KeyboardEntry|null
   *   Os dados do comando registrado ou nulo caso o comando não seja
   *   proveniente de um teclado da SGBRAS
   */
  public function parse(string $rs232Data): ?KeyboardEntry
  {
    // Determina a quantidade do separador '|'
    $amount = substr_count($rs232Data, '|');

    if (($amount == 2) || ($amount == 3)) {
      // Temos a quantidade de separadores corretos, analisa cada parte
      $partsOfCommand = explode('|', $rs232Data);

      if (strtoupper($partsOfCommand[0]) !== 'SGBRAS') {
        // Não é um comando da SGBRAS, então ignora
        return null;
      }

      if (preg_match('/[^A-Za-z_]/', $partsOfCommand[1])) {
        // O comando está mal-formado
        $eventType = EventType::UNKNOWN;
        $stopType = StopType::NONE;
        $message = null;
      } else {
        // Recuperamos a ação e os dados complementares (se disponível)
        $action     = $partsOfCommand[1];
        $complement = array_key_exists(3, $partsOfCommand)
          ? $partsOfCommand[3]
          : null
        ;

        // A partir destes dados, interpretamos o evento
        list($eventType, $stopType, $message) =
          $this->parseEvent($action, $complement)
        ;
      }

      $event = new KeyboardEntry(
        $partsOfCommand[0],
        intval($partsOfCommand[2]),
        $eventType,
        $stopType,
        $message
      );

      return $event;
    }
    
    return null;
  }

  /**
   * Permite analisar e recuperar o tipo de evento ocorrido no teclado,
   * bem como o tipo de parada, caso seja uma parada.
   * 
   * @param string $action
   *   A ação executada no teclado
   * @param string $complement
   *   Os dados complementares, se disponíveis
   * 
   * @return array
   *   Uma matriz com a informação do tipo de evento, bem como do tipo
   *   de parada (se houver) em função da ação executada no teclado
   *
   * @throws InvalidArgumentException
   *   Em caso de alguma ação ou complemento serem inválidos para o
   *   sistema SGBRAS
   */
  private function parseEvent(string $action, ?string $complement) {
    // Inicialmente não temos nenhum tipo de parada definido
    $stopType = StopType::NONE;
 
    switch ($action) {
      case 'MATRICULA':
        $eventType = EventType::IDENTIFY;

        break;
      case 'ATALHO':
        // Analisamos a função
        switch ($complement) {
          case 'Inicio da Jornada':
            // Devolvemos a ocorrência do evento de início de jornada
            $eventType = EventType::START;

            break;
          case 'Fim Jornada':
            // Devolvemos a ocorrência do evento de fim de jornada
            $eventType = EventType::FINISH;

            break;
          case 'Parada Espera':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::STANDBY;

            break;
          case 'Parada Refeicao':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::FEED;

            break;
          case 'Parada Descanso':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::REST;

            break;
          case 'Parada Abastecimento':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::FUEL;

            break;
          case 'Parada Manutencao':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::REPAIR;

            break;
          case 'Parada Acidente':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::ACCIDENT;

            break;
          case 'Parada Outros':
            // Devolvemos a ocorrência do evento de parada
            $eventType = EventType::STOP;
            $stopType = StopType::OTHER;

            break;
          case 'Reinicio de Viagem':
            // Devolvemos a ocorrência do evento de reinício de viagem
            $eventType = EventType::RESTART;

            break;
          default:
            // Indicamos que não foi possível determinar o tipo de
            // evento
            $eventType = EventType::UNKNOWN;

            break;
          }

        break;
      case 'DADO_LIVRE':
        $eventType = EventType::MESSAGE;
        $message = $complement;

        break;
      default:
        // Indicamos que não foi possível determinar o tipo de
        // evento
        $eventType = EventType::UNKNOWN;

        break;
    }

    return [
      $eventType, $stopType,
      ($eventType === EventType::MESSAGE ? $message : null)
    ];
  }
}