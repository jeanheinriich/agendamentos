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
 * Interface para um analisador de eventos de viagens.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip;

use Carbon\Carbon;

interface EventParser
{
  /**
   * O método que nos permite adicionar uma função a ser executada
   * sempre que os dados de uma viagem forem preenchidos.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnRoadTripCompleted(callable $callback): void;

  /**
   * Analisa cada registro de evento, fazendo a transição de estado para
   * garantirmos que os eventos estão ocorrendo em ordem correta. Os
   * eventos são agrupados em viagens que podem ser, ao final,
   * recuperados de forma a podermos produzir um relatório.
   *
   * @param int $registreID
   *   O número de identificação do registro
   * @param int $driverID
   *   A identificação do motorista responsável no momento do evento
   * @param string $plate
   *   A placa do veículo
   * @param Carbon $eventDate
   *   A data/hora do evento
   * @param string $rs232Data
   *   Os dados provenientes da interface RS232
   * @param string $location
   *   A localização onde este evento ocorreu
   */
  public function parse(int $registreID, int $driverID, string $plate,
    Carbon $eventDate, string $rs232Data, string $location): void;

  /**
   * Encerra qualquer viagem que esteja em aberto ao final do
   * processamento dos registros de eventos.
   */
  public function close(): void;
}