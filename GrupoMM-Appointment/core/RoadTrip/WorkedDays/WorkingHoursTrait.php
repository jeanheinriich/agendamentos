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
 * Essa é uma trait (característica) simples de funções para manipulação
 * dos horários de trabalho a serem cumpridos (horário diúrno, horário
 * noturno e jornada de trabalho a ser cumprida).
 * 
 * A jornada de trabalho do empregado é, por regra, de 8 horas diárias e
 * 44 horas semanais. Contudo, existe diferença se essas horas forem
 * cumpridas durante o dia ou à noite. A jornada de trabalho noturna
 * deve ser paga com valor diferenciado e tendo um “tempo” diferente.
 * 
 * Para efeito de consideração nos cálculos internos no sistema,
 * precisamos estipular os horário da jornada noturna e diurna.
 * 
 * A jornada de trabalho noturno engloba o período das 22h às 5h, com
 * algumas exceções. Para trabalhadores rurais, o horário noturno é das
 * 21h às 5h, se trabalharem com agricultura. Para os trabalhadores
 * pecuaristas, o trabalho noturno é das 20h às 4h. Para os portuários,
 * a jornada noturna vai das 19h às 7h.
 *
 * Esta trait está apta a trabalhar com jornadas fixas por dia, e não
 * com jornadas de trabalho em escala.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip\WorkedDays;

use Carbon\Carbon;

trait WorkingHoursTrait
{
  /**
   * O horário de início do período diurno.
   * 
   * @var Carbon
   */
  protected $startDayTime;

  /**
   * O horário de encerramento do período diurno.
   * 
   * @var Carbon
   */
  protected $endDayTime;

  /**
   * O horário de início do período noturno.
   * 
   * @var Carbon
   */
  protected $startNightTime;

  /**
   * O horário de encerramento do período noturno.
   * 
   * @var Carbon
   */
  protected $endNightTime;

  /**
   * As informações dos horários de trabalho (diurno e noturno). A chave
   * é a data de início da validade deste horário.
   * 
   * @var array
   */
  protected $workingHours;

  /**
   * A duração da viagem neste dia de trabalho.
   *
   * @var int
   */
  protected $journeyLength = 0;

  /**
   * Flag que sinaliza se deve-se descontar horas trabalhadas à menos do
   * banco de horas.
   * 
   * @var bool
   */
  protected $discountWorkedLessHours = false;

  /**
   * Seta as informações de jornadas de trabalho a serem cumpridas em
   * função de uma data;
   *
   * @param array $workingHours
   *   Os dados de jornadas de trabalho a serem cumpridas
   */
  public function setWorkingHours(array $workingHours): void
  {
    $this->workingHours = $workingHours;
  }

  /**
   * Atualiza os horários de início e término das jornadas de trabalho e
   * a quantidade de tempo da jornada de trabalho a ser cumprida em
   * função de uma data informada.
   *
   * @param Carbon $date
   *   A data na qual iremos trabalhar
   */
  protected function updateWorkingHours(Carbon $date): void
  {
    // Determina as jornadas de trabalho a ser cumprida
    $selectedWorkingHours = null;
    if ($this->workingHours) {
      $requestedDate = $date->copy()->startOfDay();
      foreach ($this->workingHours as $begginingAt => $workingHour) {
        // Verificamos qual horário de trabalho atende à data informada
        $workingHourDate = Carbon::createFromFormat('Y-m-d H:i:s',
          $begginingAt . ' 00:00:00')->locale('pt_BR')
        ;
        if ($workingHourDate->lessThanOrEqualTo($requestedDate)) {
          $selectedWorkingHours = $workingHour;

          break;
        }
      }
    }

    if ($selectedWorkingHours) {
      // Utilizamos os horários selecionados
      $workingHours = $selectedWorkingHours;
    } else {
      // Definimos um horário padrão, caso não existe um horário de
      // trabalho definido para a data informada. A jornada de trabalho
      // noturno engloba o período das 22h às 5h (na maioria dos casos),
      // então usamos este valor como padrão
      $workingHours = [
        'startdaytime' => '05:00:00',
        'startnighttime' => '22:00:00',
        'days' => [
          0 => 0,
          1 => 28800,
          2 => 28800,
          3 => 28800,
          4 => 28800,
          5 => 28800,
          6 => 14400
        ],
        'discountWorkedLessHours' => false
      ];
    }

    // Determinamos os períodos de início e fim dos horários diurno e
    // noturno conforme esta jornada
    $this->startDayTime = $date->copy()
      ->setTimeFromTimeString($workingHours['startdaytime'])
    ;
    $this->startNightTime = $date->copy()
      ->setTimeFromTimeString($workingHours['startnighttime'])
    ;
    $this->endDayTime = $this->startNightTime->copy()->subSecond();
    $this->endNightTime = $this->startDayTime->copy()->subSecond();
    $this->out("O horário diurno se inicia às ",
      $workingHours['startdaytime']
    );
    $this->out("O horário noturno se inicia às ",
      $workingHours['startnighttime']
    );

    // Determinamos a duração da jornada neste dia
    $dayOfWeek = $date->dayOfWeek;
    $this->journeyLength = $workingHours['days'][$dayOfWeek];
    $this->out("A duração da jornada de trabalho é de ",
      $this->formatDuration($this->journeyLength), " (",
      $this->journeyLength, " segundos)"
    );

    $this->discountWorkedLessHours = $workingHours['discountWorkedLessHours'];
    if ($this->discountWorkedLessHours) {
      $this->out("Descontar horas trabalhadas à menos do banco de "
        . "horas"
      );
    }
  }

  /**
   * Separa do período informado os tempos que pertencem aos horários
   * diurno e noturno respectivamente.
   * 
   * @param Carbon $startOfPeriod
   *   A data/hora do início do período
   * @param Carbon $endOfPeriod
   *   A data/hora do término do período
   * 
   * @return array
   *   Os tempos (em segundos) pertencentes aos horários diurno e
   * noturno respectivamente
   */
  protected function separateDayAndNightHours(Carbon $startOfPeriod,
    Carbon $endOfPeriod)
  {
    // Inicializa nossos parâmetros
    $dayTime   = 0;
    $nightTime = 0;
    $start     = $startOfPeriod->copy();
    $end       = $endOfPeriod->copy();

    // Separamos deste período de tempo àquele que ocorre antes do
    // início do horário diurno
    if ($start->lessThan($this->startDayTime)) {
      // O início do período analisado ocorreu ainda no período de
      // horário noturno, então verifica se o término do período ocorreu
      // também antes do início do horário diurno
      if ($end->lessThan($this->startDayTime)) {
        // Todo o período analisado ocorreu no horário noturno, então
        // calcula e retorna
        $nightTime = $start->diffInSeconds($end);

        return [
          $dayTime,
          $nightTime
        ];
      } else {
        // O término do período analisado ocorreu após o início do
        // horário diurno, então determinamos a porção correspondente ao
        // horário noturno, que se dá entre o início deste período
        // analisado até o início do horário diurno
        $nightTime = $start->diffInSeconds($this->endNightTime);
      }

      // Ajustamos o início do período analisado para o início do
      // horário diurno para prosseguir a análise
      $start = $this->startDayTime->copy();
    }

    // Separamos deste período de tempo àquele que ocorre no horário
    // diurno
    if ($start->between($this->startDayTime, $this->endDayTime)) {
      // O início do período analisado ocorreu durante o horário diurno,
      // então verifica se o término deste período também ocorreu no
      // horário diurno
      if ($end->lessThan($this->startNightTime)) {
        // Todo o período analisado ocorreu no horário diurno, então
        // calcula e retorna
        $dayTime = $start->diffInSeconds($end);

        return [
          $dayTime,
          $nightTime
        ];
      } else {
        // O término do período analisado ocorreu após o início do
        // horário noturno, então determinamos a porção correspondente
        // ao horário diurno, que se dá entre o início deste período
        // analisado até o início do horário noturno
        $dayTime = $start->diffInSeconds($this->endDayTime);
      }

      // Ajustamos o início do período analisado para o início do
      // horário noturno para prosseguir a análise
      $start = $this->startNightTime->copy();
    }

    // Separamos deste período de tempo àquele que ocorre no horário
    // noturno e acrescentamos
    $nightTime += $start->diffInSeconds($end);
    
    return [
      $dayTime,
      $nightTime
    ];
  }
}
