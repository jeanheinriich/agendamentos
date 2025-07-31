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
 * Essa é uma classe que permite calcular as horas trabalhadas baseado
 * nas informações de viagens executadas por um motorista. Analisa os
 * eventos ocorridos (início de viagem, paradas, mensagens, etc) e
 * determinar as horas de trabalho, horas extras, horário diurno e
 * horário noturno.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\WorkedDays;

use Carbon\Carbon;
use Core\Helpers\ClassNameTrait;
use Core\Helpers\DebuggerTrait;
use Core\Helpers\FormatterTrait;
use Core\RoadTrip\NightHours;
use Core\RoadTrip\EventParser;
use Core\RoadTrip\EventType;
use Core\RoadTrip\RoadTripStatus;
use Core\RoadTrip\StopType;

class WorkedDayAnalyzer
{
  // As funções de recuperação do nome de uma classe sem o namespace
  use ClassNameTrait;

  // As funções de depuração desta classe que nos permite escapar em
  // formato HTML o processamento, permitindo uma melhor análise do
  // comportamento
  use DebuggerTrait;

  // As funções de formatação
  use FormatterTrait;

  // As funções de manipulação da jornada de trabalho
  use WorkingHoursTrait;

  /**
   * O ID do motorista para o qual estamos analisando os eventos.
   *
   * @var integer
   */
  protected $currentDriverID = 0;

  /**
   * O dia de trabalho que estamos analisando.
   * 
   * @var Carbon
   */
  protected $currentWorkday;

  /**
   * Os eventos deste dia de trabalho
   * 
   * @var array
   */
  protected $events = [];

  /**
   * Os totalizadores de tempo (em segundos) para a dia de trabalho
   * sendo analisado
   * 
   * @var array
   */
  protected $dayTimes = [
    'driving' => 0,
    'waiting' => 0,
    'feeding' => 0,
    'resting' => 0,
    'stopped' => 0,
    'dayshift' => 0,
    'nightshift' => 0,
    'worked' => 0,
    'overtime' => 0
  ];

  /**
   * Os totalizadores de tempo (em segundos) para a período de trabalho
   * sendo analisado
   * 
   * @var array
   */
  protected $periodTimes = [
    'driving' => 0,
    'waiting' => 0,
    'feeding' => 0,
    'resting' => 0,
    'stopped' => 0,
    'dayshift' => 0,
    'nightshift' => 0,
    'worked' => 0,
    'overtime' => 0
  ];

  /**
   * O conversor dos nomes dos totalizadores de tempos calculados numa
   * viagem para o totalizador de tempos do dia trabalhado.
   *
   * @var array
   */
  protected $toTimesKey = [
    'driving' => 'driving',
    'standby' => 'waiting',
    'feed' => 'feeding',
    'rest' => 'resting',
    'stopped' => 'stopped'
  ];

  /**
   * Os nomes dos dias da semana
   * 
   * @var array
   */
  protected $dayOfWeekName = [
    'Domingo',
    'Segunda',
    'Terça',
    'Quarta',
    'Quinta',
    'Sexta',
    'Sábado'
  ];

  /**
   * Flag que determina se devemos ignorar paradas incompletas. Caso
   * esteja ignorando, o tempo será acrescentado ao trecho imediatamente
   * anterior, senão o tempo da parada será considerado até o evento
   * subsequente.
   *
   * @var boolean
   */
  protected $bypassIncompleteStops = false;

  /**
   * Flag que determina se devemos ignorar viagens incompletas. Caso
   * esteja ignorando, toda a viagem será ignorada, senão tenta
   * aproveitar os tempos que foram descritos.
   *
   * @var boolean
   */
  protected $bypassIncompleteRoadTrip = false;

  /**
   * Os dias de trabalho computados.
   *
   * @var array
   */
  protected $workedDays = [];

  /**
   * O analisador de eventos de viagens.
   *
   * @var EventParser
   */
  protected $roadtrip;

  /**
   * O construtor de nossa viagem
   */
  public function __construct(EventParser $roadtrip)
  {
    $this->debug = false;
    $this->roadtrip = $roadtrip;

    // Seta uma função para lidar com os dados de uma viagem completa
    $this->roadtrip->setOnRoadTripCompleted([$this, 'onRoadTripCompleted']);
  }

  /**
   * A função responsável por processar os dados de cada viagem,
   * calculando os valores de horas trabalhadas.
   *
   * @param array $roadTripData
   *   Os dados da viagem
   */
  public function onRoadTripCompleted(array $roadTripData): void
  {
    $this->out("<b>Recebido os dados de viagem:</b>");
    $this->out($roadTripData);

    // Ignoramos viagens incompletas, porém aceitamos parcialmente
    // completas
    if ($roadTripData['status'] !== RoadTripStatus::COMPLETED_SUCCESSFULLY) {
      if ($roadTripData['status'] !== RoadTripStatus::COMPLETED_PARTIALLY) {
        if ($this->bypassIncompleteRoadTrip) {
          $this->out("Ignorando jornada incompleta");

          return;
        } else {
          if ($roadTripData['events'][0]['eventType'] !== EventType::START) {
            $this->out("Ignorando jornada incompleta");

            return;
          }
        }
      }
    }

    // Acrescentamos todos os eventos desta viagem ao dia de trabalho na
    // forma de trechos, onde cada um representa um período em que o
    // motorista esteve trabalhando ou parado (independente do motivo)
    foreach ($roadTripData['events'] as $tripEvent) {
      // Ignoramos eventos que foram descartados
      if ($tripEvent['ignore']) {
        continue;
      }

      // Ignoramos eventos de mensagens pois os mesmos não são usados
      // para computarmos tempos trabalhados
      if ($tripEvent['eventType'] === EventType::MESSAGE) {
        $this->out('Evento de mensagem ignorado');
        continue;
      }

      if (is_null($this->currentWorkday) ||
          !$tripEvent['datetime']->isSameDay($this->currentWorkday)) {
        // Esta viagem teve início em um dia diferente, ou este é o
        // primeiro dia de trabalho, então precisamos atualizar o nosso
        // dia de trabalho
        
        // Gravamos quaisquer informações do dia anterior
        $this->storeWorkedDayData();

        // Iniciamos um novo dia
        $this->startNewDay($tripEvent['datetime']);
      }

      // Formatamos o nome do tipo do evento
      $eventTypeName = ($tripEvent['stopType'] > 0)
        ? StopType::readable($tripEvent['stopType'])
        : EventType::readable($tripEvent['eventType'])
      ;

      // Adicionamos o trecho desta viagem, separando (se for o caso) os
      // valores por dia
      $start = $tripEvent['datetime']->copy();
      $lastEvent = null;
      do {
        $repeat = false;
        if (is_null($tripEvent['endtime'])) {
          // Estamos lidando com um evento de uma viagem incompleta,
          // então simplesmente ignoramos
          continue;
        }
        if (!$tripEvent['endtime']->isSameDay($this->currentWorkday)) {
          // Este trecho do dia trabalhado ultrapassou o dia em que
          // estamos analisando, então precisamos segmentar ele para que
          // os valores sejam atribuídos corretamente à cada dia de
          // trabalho respectivamente. Para isto, determinamos como o
          // final do trecho o final do dia de trabalho corrente
          $finish = $this->currentWorkday->copy()->endOfDay();
        } else {
          $finish = $tripEvent['endtime']->copy();
        }
        $this->out('Trecho iniciando em ', $start, ' e terminando em ',
          $finish
        );
        
        // Computamos os tempos entre o horário do início e término
        // deste trecho
        $duration = $start->diffInSeconds($finish);
        list($dayTime, $nightTime) = $this->separateDayAndNightHours(
          $start, $finish
        );

        if ($this->bypassIncompleteStops && $tripEvent['incomplete']) {
          // Precisamos ignorar este evento incompleto. Para isto
          // computa os valores deste período como sendo o do período
          // imediatamente anterior
          $eventType   = $lastEvent['eventType']; 
          $stopType    = $lastEvent['stopType'];

          if ($stopType > 0) {
            // Modificamos o término do último evento para que ele englobe
            // este evento também
            $this->out('Acrescentando a duração deste evento no trecho '
              . 'anterior'
            );
            $last = count($this->events) - 1;
            $this->events[ $last ]['end'] = $finish->format('H:i:s');
            $this->events[ $last ]['duration'] += $duration;
          }
        } else {
          $eventType = $tripEvent['eventType']; 
          $stopType  = $tripEvent['stopType'];

          if ($tripEvent['eventType'] !== EventType::RESTART) {
            // Acrescentamos este trecho da jornada
            $this->events[] = [
              'time'     => $start->format('H:i:s'),
              'end'      => $finish->format('H:i:s'),
              'type'     => $tripEvent['eventType'],
              'stopType' => $tripEvent['stopType'],
              'typeName' => $eventTypeName,
              'location' => $tripEvent['location'],
              'duration' => $tripEvent['eventType'] === EventType::FINISH
                ? $this->formatDuration($roadTripData['duration'])
                : $this->formatDuration($duration),
              'plate'    => $roadTripData['plate']
            ];
          }

          $lastEvent = $tripEvent;
        }

        // Em função do tipo deste trecho, computa os valores
        // apropriadamente
        if ($stopType > 0) {
          if ($stopType > StopType::REST) {
            // As paradas por abastecimento, manutenção, acidente e
            // outros tipos de parada são computadas simplesmente como
            // tempo parado
            $totalizerName = 'stopped';
          } else {
            $totalizerName = $this->toTimesKey[
              StopType::toString($stopType)
            ];
          }
        } else {
          $totalizerName = 'driving';
        }
        $this->dayTimes[ $totalizerName ] += $duration;
        if ($eventType === EventType::STOP) {
          // É uma parada, então analisa conforme o tipo
          switch ($stopType) {
            case StopType::STANDBY:
            case StopType::FEED:
            case StopType::REST:
              // As paradas paradas em espera, alimentação e descanso
              // não são computadas neste momento
              break;
            default:
              // Todos os demais tipos de paradas são computados como
              // horas trabalhadas
              $this->dayTimes['dayshift'] += $dayTime;
              $this->dayTimes['nightshift'] += $nightTime;
          } 
        } else {
          // Os tempos em direção são computados como horas
          // trabalhadas
          $this->dayTimes['dayshift'] += $dayTime;
          $this->dayTimes['nightshift'] += $nightTime;
        }

        if (!$tripEvent['endtime']->isSameDay($this->currentWorkday)) {
          // Tornamos o início do próximo dia como o início do novo
          // trecho e repetimos o loop para que os demais valores de
          // tempo sejam computados no dia subsequente
          $start = $finish->copy()->addSecond()->startOfDay();
          $repeat = true;
          
          // Gravamos quaisquer informações do dia de trabalho corrente
          $this->storeWorkedDayData();

          // Iniciamos um novo dia
          $this->startNewDay($start);
        }
      } while ($repeat);
    }
  }

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
    Carbon $eventDate, string $rs232Data, string $location): void
  {
    $this->roadtrip->parse($registreID, $driverID, $plate, $eventDate,
      $rs232Data, $location
    );
  }

  /**
   * Encerra qualquer viagem que esteja em aberto ao final do
   * processamento dos registros de eventos.
   */
  public function close(): void
  {
    // Ao final, garantimos que qualquer viagem ainda não encerrada seja
    // computada
    $this->roadtrip->close();

    // Gravamos quaisquer informações do dia de trabalho corrente
    $this->storeWorkedDayData();
  }

  /**
   * Recupera a informação de dias trabalhados.
   *
   * @return array
   */
  public function getWorkedDays(): array
  {
    $this->out('Dias trabalhados:');
    $this->out($this->workedDays);
    return $this->workedDays;
  }

  /**
   * Recupera a informação dos totalizadores dos dias trabalhados.
   *
   * @return array
   */
  public function getTotalizers(): array
  {
    $this->out('Totalizadores do período de trabalho:');
    $totalizers = [];
    foreach ($this->periodTimes as $key => $value) {
      $totalizers[ $key ] = $this->formatDuration($value);
      $this->out($key, ': ', $totalizers[ $key ], ' (', $value, ')');
    }

    return $totalizers;
  }

  /**
   * Inicializa os dados para um novo dia de trabalho a ser analisado.
   * 
   * @param Carbon $date
   *   A data do novo dia de trabalho
   */
  protected function startNewDay(Carbon $date): void
  {
    $this->out("Iniciando dia de trabalho em "
      . $date->format('d/m/Y'))
    ;

    // Avançamos para o novo dia
    $this->currentWorkday = $date->copy();

    // Atualizamos as informações dos tempos da jornada de trabalho
    $this->updateWorkingHours($date);

    // Zeramos nossos contadores de tempo
    $this->dayTimes =  array_map(
      function($value) { return 0; }, $this->dayTimes
    );
  }

  /**
   * Armazena as informações do dia de trabalho.
   */
  protected function storeWorkedDayData(): void
  {
    if (count($this->events) > 0) {
      // Armazenamos as informações deste dia de trabalho

      // Primeiramente calculamos as horas trabalhadas e eventuais horas
      // extras em função da jornada executada. Para isto, somamos as
      // horas diurnas e noturnas
      $nightHours = new NightHours();
      $duration = $this->dayTimes['dayshift']
        + $nightHours->toDayHours($this->dayTimes['nightshift'])
      ;
      if ($duration > $this->journeyLength) {
        // A duração da jornada de trabalho foi superior ao valor de
        // horas deste dia de trabalho, então precisamos computar as
        // horas extras
        $this->dayTimes['worked']   = $this->journeyLength;
        $this->dayTimes['overtime'] = $duration - $this->journeyLength;
      } else {
        // A duração da jornada de trabalho foi igual ou inferior ao 
        // valor de horas deste dia de trabalho
        $this->dayTimes['worked']   = $duration;
        if ($this->discountWorkedLessHours) {
          // Precisamos descontar horas trabalhadas à menos do banco de
          // horas
          $this->dayTimes['overtime'] = $duration - $this->journeyLength;
        } else {
          $this->dayTimes['overtime'] = 0;
        }
      }

      $this->out('Tempos totalizados para o dia ',
        $this->currentWorkday->isoFormat('LL'), ': '
      );
      $this->out('Horas trabalhadas: ', $this->formatDuration(
        $this->dayTimes['worked'])
      );
      $this->out('Horas extras/banco de horas: ', $this->formatDuration(
        $this->dayTimes['overtime'])
      );
      $this->out('Horas trabalhadas diurnas: ', $this->formatDuration(
        $this->dayTimes['dayshift'])
      );
      $this->out('Horas trabalhadas noturnas: ', $this->formatDuration(
        $this->dayTimes['nightshift'])
      );

      // Geramos os dados deste dia de trabalho
      $workedDayData = [
        'day'       => $this->currentWorkday->format('d/m/Y'),
        'dayOfWeek' => $this->dayOfWeekName[ $this->currentWorkday->dayOfWeek ],
        'events'    => $this->events,
      ];

      // Acrescentamos os tempos calculados no totalizador do período e
      // convertemos os valores para serem retornados
      $this->out('Totalizadores:');
      foreach ($this->dayTimes as $key => $value) {
        $this->periodTimes[ $key ] += $value;
        $workedDayData[ $key ] = $this->formatDuration($value);
        $this->out($key, ': ', $workedDayData[ $key ], ' (', $value,
          ')'
        );
      }

      $this->out('Eventos:');
      $this->out($this->events);

      // Adicionamos este dia de trabalho à relação de dias trabalhados
      $this->workedDays[] = $workedDayData;

      // Zeramos a relação de eventos
      $this->events = [];
    }
  }
}
