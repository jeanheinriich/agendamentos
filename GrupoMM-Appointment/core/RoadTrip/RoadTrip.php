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
 * Essa é uma classe que permite lidar com as informações de uma viagem
 * executada por um motorista. Permite lidar com os eventos ocorridos
 * (início de viagem, paradas, mensagens, etc) e determinar se a viagem
 * foi corretamente informada (a viagem possui um início e término, bem
 * como para cada parada temos o respectivo reinício de viagem).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\RoadTrip;

use Carbon\Carbon;
use Core\Helpers\ClassNameTrait;
use Core\Helpers\DebuggerTrait;
use Core\Helpers\FormatterTrait;
use Core\RoadTrip\Keyboard\KeyboardAdapter;
use Core\RoadTrip\Exceptions\IllegalStateTransitionException;
use Core\RoadTrip\States\{Finished, Restarted, Started, Stopped, Unknown};
use InvalidArgumentException;

class RoadTrip
  implements EventParser
{
  // As funções de recuperação do nome de uma classe sem o namespace
  use ClassNameTrait;

  // As funções de depuração desta classe que nos permite escapar em
  // formato HTML o processamento, permitindo uma melhor análise do
  // comportamento
  use DebuggerTrait;

  // As funções de formatação
  use FormatterTrait;

  /**
   * O analisador de comandos do oriundos do teclado.
   *
   * @var KeyboardAdapter
   */
  protected $keyboard;

  /**
   * O estado em que esta viagem se encontra em relação à sua execução.
   * É utilizado para analisar o cumprimento de todas as etapas de uma
   * viagem (início, paradas e término).
   *
   * @var RoadTripState
   */
  protected $state;

  /**
   * As condições da execução desta viagem como um todo. Indica se o
   * motorista cumpriu todas as etapas desta viagem.
   *
   * @var int
   */
  protected $enforcementStatus;

  /**
   * Os eventos desta viagem.
   *
   * @var array
   */
  protected $events = [];

  /**
   * O índice do último evento válido.
   *
   * @var int|null
   */
  protected $lastEventIndex = null;

  /**
   * A flag que sinaliza que as viagens devem ser montadas como uma
   * lista de eventos.
   *
   * @var boolean
   */
  protected $buildAsEventList = false;

  /**
   * Os eventos para os quais o índice do último evento válido pode
   * apontar e para os quais iremos computar tempos de viagem
   *
   * @var array
   */
  protected $eventsToCalculate = [
    EventType::START,
    EventType::STOP,
    EventType::RESTART
  ];

  /**
   * Os tempos (em segundos) para cada tipo de parada e/ou none para o
   * tempo de direção
   *
   * @var array
   */
  protected $tripTimes = [
    StopType::NONE => 0,
    StopType::STANDBY => 0,
    StopType::FEED => 0,
    StopType::REST => 0,
    StopType::FUEL => 0,
    StopType::REPAIR => 0,
    StopType::ACCIDENT => 0,
    StopType::OTHER => 0
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
   * As viagens armazenadas.
   *
   * @var array
   */
  protected $trips = [];

  /**
   * O ID do motorista para o qual estamos analisando os eventos.
   *
   * @var integer
   */
  protected $currentDriverID = 0;

  /**
   * A placa do veículo para o qual estamos analisando os eventos.
   *
   * @var string
   */
  protected $currentPlate = '';

  /**
   * A função a ser executada sempre que os dados de uma viagem forem
   * preenchidos.
   *
   * @var callable
   */
  protected $onRoadTripCompleted;

  /**
   * A flag que indica que eventos consecutivos de paradas que ocorram
   * em um curto espaço de tempo (menos do que 10 minutos) podem ser
   * considerados erros de digitação do motorista. Será considerado o
   * último como o correto.
   *
   * @var boolean
   */
  protected $bypassConsecutiveStops = true;

  /**
   * A flag que indica que um início de viagem ocorrido quando temos uma
   * viagem incompleta e estamos em uma parada deve ser tratado como o
   * reinício de viagem.
   *
   * @var boolean
   */
  protected $convertStartToRestartIfAtStop = true;

  /**
   * O construtor de nosso analisador de eventos de viagem.
   *
   * @param KeyboardAdapter $keyboard
   *   O analisador de comandos do oriundos do teclado
   */
  public function __construct(KeyboardAdapter $keyboard)
  {
    $this->debug = false;
    $this->keyboard = $keyboard;

    // Marcamos que o estado inicial da viagem está desconhecido pois
    // não temos nenhum dado processado
    $this->enforcementStatus = RoadTripStatus::UNKNOWN;

    // Indica que neste momento o estado de nossa viagem é desconhecido
    $this->setState(new Unknown());
  }

  /**
   * Habilita o modo de geração das viagens como uma lista de eventos.
   */
  public function setBuildAsEventList(): void
  {
    $this->buildAsEventList = true;
  }

  /**
   * O método que nos permite adicionar uma função a ser executada
   * sempre que os dados de uma viagem forem preenchidos.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnRoadTripCompleted(callable $callback): void
  {
    $this->onRoadTripCompleted = $callback;
  }

  /**
   * Recupera o estado em que esta viagem se encontra.
   *
   * @return RoadTripState
   */
  public function getState(): RoadTripState
  {
    return $this->state;
  }

  /**
   * Define o estado em que esta jornada se encontra. Os estados nos
   * permitem definir se esta viagem possui todos os eventos (está
   * completa). A mudança de estado ocorre à cada evento processado.
   *
   * @param RoadTripState $state
   *   O estado para o qual se deseja mudar
   */
  protected function setState(RoadTripState $state): void
  {
    $this->out("Alterando o estado da viagem para '",
      $this->getNameOfClass($state), "'"
    );

    // Atribuímos o novo estado desta viagem
    $this->state = $state;
  }

  /**
   * Determina se esta viagem está iniciada.
   *
   * @return bool
   */
  public function isStarted(): bool
  {
    return ($this->state instanceof Started)
      || ($this->state instanceof Stopped)
      || ($this->state instanceof Restarted)
    ;
  }

  /**
   * Determina se estamos num evento de parada.
   *
   * @return bool
   */
  public function isAtStop(): bool
  {
    return $this->state instanceof Stopped;
  }

  /**
   * Determina, de acordo com o estado, se o motorista está dirigindo.
   *
   * @return bool
   */
  public function isDriving(): bool
  {
    return ($this->state instanceof Started)
      || ($this->state instanceof Restarted);
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
    $this->simpleOut("<style>p { margin-block-start: .2em; "
      . "margin-block-end: .2em;}</style>"
    );
    $this->out("<hr>Analisando comando: [",
      "<span style={$this->valueStyle}>", $rs232Data, "</span>]"
    );

    // Cada dado obtido pela interface serial (RS232) do rastreador deve
    // ser analisado conforme o modelo de teclado nele acoplado. Com
    // isto iremos obter um registro do evento recebido pelo teclado
    $keyboardEntry = $this->keyboard->parse($rs232Data);
    if (!$keyboardEntry) {
      $this->out("O comando foi rejeitado pelo parse de teclado");

      return;
    }

    // Temos dados do teclado, então geramos um novo evento
    $this->out("Processando evento <b>'",
      EventType::toString($keyboardEntry->type), "'</b> ocorrido ",
      "em <b>", $eventDate, "</b>"
    );
    $event = new EventEntry($registreID, $eventDate, $location,
      $keyboardEntry);


    if ($event->type === EventType::IDENTIFY) {
      // Eventos de identificação do condutor são ignorados pois não
      // são utilizados no processo de análise
      $this->out("Ignorando evento de identificação");

      return;
    }

    // Verificamos se ocorreu mudança de motorista
    if ($this->currentDriverID !== $driverID) {
      $this->out("ID do motorista modificado de ",
        $this->currentDriverID, " para ", $driverID
      );
      $this->out("Placa modificada de ", $this->currentPlate,
        " para ", $plate
      );

      // Verificamos se temos dados processados
      if (count($this->events) > 0) {
        // Temos um ou mais eventos, então devemos considerar que a
        // viagem atual está incompleta
        $this->out("Finalizando viagem incompleta");
        $this->enforcementStatus = RoadTripStatus::INCOMPLETE;

        // Concluímos a viagem
        $this->endRoadTrip();
      }

      // Definimos as informações de motorista e placa do veículo
      $this->currentDriverID = $driverID;
      $this->currentPlate    = $plate;
    }

    // Verificamos se ocorreu mudança de placa do veículo
    if ($this->currentPlate !== $plate) {
      $this->out("Placa modificada de ", $this->currentPlate,
        " para ", $plate
      );

      // Verificamos se temos dados processados
      if (count($this->events) > 0) {
        // Temos um ou mais eventos, então devemos considerar que a
        // viagem atual está incompleta
        $this->out("Finalizando viagem incompleta");
        $this->enforcementStatus = RoadTripStatus::INCOMPLETE;

        // Concluímos a viagem
        $this->endRoadTrip();
      }

      // Definimos a placa do veículo
      $this->currentPlate = $plate;
    }

    // O indicativo se completamos a viagem
    $storeRoadTrip = false;

    // Analisamos conforme o tipo de evento usando uma máquina de
    // estados, nos permitindo garantir que os eventos estejam
    // ocorrendo em ordem correta
    switch ($event->type) {
      case EventType::MESSAGE:
        // Comando de mensagem livre
        if ($this->isStarted() === false) {
          // Não temos uma viagem ainda iniciada, então simplesmente
          // ignora
          $this->out("Ignorando evento de mensagem");

          return;
        }

        break;
      case EventType::START:
        // Comando de início de viagem

        // Verifica se precisamos lidar com comandos de início de viagem
        // incorretos
        $analyze = true;
        if ($this->convertStartToRestartIfAtStop) {
          if ($this->enforcementStatus !== RoadTripStatus::INCOMPLETE) {
            if ($this->isAtStop()) {
              // Forçamos o estado inicial da viagem
              $this->out("Convertendo início em reinício de viagem");
              $this->setState(new Restarted());
              $analyze = false;

              $this->out("<b>Viagem reiniciada</b>");
            }
          }
        }

        if ($analyze) {
          // Verificamos se temos dados processados
          if (count($this->events) > 0) {
            // Temos um ou mais eventos, então devemos considerar que
            // a viagem atual está incompleta
            $this->out("Finalizando viagem incompleta");
            $this->enforcementStatus = RoadTripStatus::INCOMPLETE;

            // Concluímos a viagem
            $this->endRoadTrip();
          }

          try {
            // Fazemos a transição para o estado, indicando que esta
            // viagem está sendo iniciada
            $this->setState($this->state->started());
          } catch (IllegalStateTransitionException $exception) {
            // A transição de estado é inválida, então forçamos o estado
            // inicial da viagem
            $this->out("Tentativa inválida de modificar o estado de '",
              $this->getNameOfClass($this->state), "' para 'Started'"
            );
            $this->out($exception->getMessage());

            // Forçamos o estado inicial da viagem
            $this->setState(new Started());
          }

          // Forçamos que o estado da viagem esteja como desconhecido
          // pois não temos nenhum dado processado
          $this->enforcementStatus = RoadTripStatus::UNKNOWN;
          $this->out("<b>Viagem iniciada</b>");
        }

        break;
      case EventType::STOP:
        // Comando de parada durante a viagem
        try {
          // Fazemos a transição para o estado, indicando que estamos
          // em uma parada
          $this->setState($this->state->stopped());
        } catch (IllegalStateTransitionException $exception) {
          // A transição de estado é inválida
          $this->out("Tentativa inválida de modificar o estado de '",
            $this->getNameOfClass($this->state), "' para 'Stopped'"
          );
          $this->out($exception->getMessage());

          // Verificamos em quais condições ela ocorreu
          if ($this->isStarted()) {
            // A viagem está iniciada, então analisamos outras
            // condições
            if ($this->isAtStop()) {
              // O motorista já estava em uma parada, então esta nova
              // parada foi um erro de digitação e/ou a correção do
              // comando anterior.
              $forceToMarkAsIgnore = true;
              $this->out("Estamos em uma parada");
              if ($this->bypassConsecutiveStops) {
                $this->out("Analisando eventos de parada consecutivos");
                if (array_key_exists($this->lastEventIndex, $this->events)) {
                  $diff = $event->eventDate->diffInMinutes(
                    $this->events[ $this->lastEventIndex ]
                      ->eventDate->copy()
                  );
                  $this->out("Intervalo entre eventos é de ", $diff,
                    " minutos"
                  );
                  if ($diff < 5) {
                    // A diferença de tempo entre os eventos é muito
                    // pequena, então consideramos que o segundo
                    // evento é uma correção. Então utiliza o tipo de
                    // parada definido neste evento para corrigir o
                    // anterior e ignora o segundo evento
                    $this->out("Intervalo considerado muito pequeno, "
                      . "então consideramos que o segundo evento é uma "
                      . "correção. Então utiliza o tipo de parada "
                      . "definido neste evento para corrigir o "
                      . "anterior e ignora este evento");
                    $this->events[ $this->lastEventIndex ]->setType(
                      $event->type
                    );
                    $event->setToIgnore();
                    $forceToMarkAsIgnore = false;
                  }
                }
              }

              if ($forceToMarkAsIgnore) {
                // Marcamos a viagem como contendo um ou mais eventos
                // parcialmente completos, porém que podem ser
                // aproveitados
                $this->out("Marcando viagem como parcialmente completa");
                $this->enforcementStatus = RoadTripStatus::COMPLETED_PARTIALLY;
                $this->events[ $this->lastEventIndex ]->setIncomplete();
              }
            }
          } else {
            // Não temos uma viagem iniciada, então marcamos a viagem
            // como incompleta
            $this->out("Marcando viagem como incompleta");
            $this->enforcementStatus = RoadTripStatus::INCOMPLETE;
          }

          // Forçamos o estado como sendo em uma parada
          $this->setState(new Stopped());
        }

        $this->out("<b>", StopType::readable($event->stopType),
          " iniciada</b>"
        );

        break;
      case EventType::RESTART:
        // Comando de reinício de viagem
        try {
          // Fazemos a transição para o estado, indicando que estamos
          // reiniciando a viagem
          $this->setState($this->state->restarted());
        } catch (IllegalStateTransitionException $exception) {
          // A transição de estado é inválida
          $this->out("Tentativa inválida de modificar o estado de '",
            $this->getNameOfClass($this->state), "' para 'Restarted'"
          );
          $this->out($exception->getMessage());

          // Verificamos em quais condições ela ocorreu
          if ($this->isStarted()) {
            // A viagem está iniciada, mas não estamos numa parada,
            // então ignoramos esta mudança, pois deve ser erro de 
            // digitação
            $this->out("Ignorando reinício de viagem");
            $event->setToIgnore();

            // Forçamos o estado inicial da viagem
            $this->setState(new Restarted());
          } else {
            // A viagem ainda havia sido iniciada e a transição de
            // estado é inválida, então verificamos se esta é a
            // primeira transição
            if ( (($this->state instanceof Finished) ||
                  ($this->state instanceof Unknown)) &&
                  (count($this->events) === 0) ) {
              // Como o primeiro evento é o de reinício de viagem,
              // pode ocorrer que o motorista tenha digitado
              // erroneamente o reinício de viagem ao invés do
              // início do mesmo, então modificamos este evento para
              // corresponder ao de início de viagem
              $this->setState(new Restarted());
              $this->out("Corrigindo automaticamente evento para "
                . "'início de viagem'"
              );
              $event->setType(EventType::START);

              // Forçamos que o estado da viagem esteja como
              // desconhecido pois não temos nenhum dado processado
              $this->enforcementStatus = RoadTripStatus::UNKNOWN;
            } else {
              if ($this->enforcementStatus === RoadTripStatus::UNKNOWN) {
                // Não temos uma viagem iniciada, então marcamos a
                // viagem como incompleta
                $this->out("Marcando viagem como incompleta");
                $this->enforcementStatus = RoadTripStatus::INCOMPLETE;
              }

              // Forçamos o estado para reiniciada
              $this->out("Forçando reinício de viagem");
              $this->setState(new Restarted());
            }
          }
        }
        $this->out("<b>Viagem reiniciada</b>");

        break;
      case EventType::FINISH:
        // Comando de fim de viagem
        try {
          // Fazemos a transição para o estado, indicando que estamos
          // finalizando a viagem
          $this->setState($this->state->finished());

          if ($this->enforcementStatus === RoadTripStatus::UNKNOWN) {
            // Marcando viagem como completa
            $this->out("Marcando viagem como completa");
            $this->enforcementStatus = RoadTripStatus::COMPLETED_SUCCESSFULLY;
          }
        } catch (IllegalStateTransitionException $exception) {
          // A transição de estado é inválida
          $this->out("Tentativa inválida de modificar o estado de '",
            $this->getNameOfClass($this->state), "' para 'Finished'"
          );
          $this->out($exception->getMessage());

          // Verificamos em quais condições ela ocorreu
          if ($this->isAtStop()) {
            if ($this->enforcementStatus === RoadTripStatus::UNKNOWN) {
              // Como todo o andamento da viagem estava correto até
              // este ponto, e estava em uma parada quando ocorreu o
              // encerramento, então acrescentamos um reinício de
              // viagem apenas para permitir concluir corretamente
              $this->out("Acrescentando reinício de viagem");
              $restart = clone $event;
              $restart->setType(EventType::RESTART);
              $this->appendEvent($restart);

              // Marcando viagem como completa
              $this->out("Marcando viagem como completa");
              $this->enforcementStatus = RoadTripStatus::COMPLETED_SUCCESSFULLY;
            } else {
              if ($this->enforcementStatus !== RoadTripStatus::INCOMPLETE) {
                // O motorista estava em uma parada, e ocorreu o término
                // de viagem, então indicamos que esta viagem contém
                // informações parcialmente completas
                $this->out("Marcando viagem como parcialmente completa");
                $this->enforcementStatus = RoadTripStatus::COMPLETED_PARTIALLY;
              }
            }
          } else {
            // Não temos uma viagem iniciada, então marcamos a viagem
            // como incompleta
            $this->out("Marcando viagem como incompleta");
            $this->enforcementStatus = RoadTripStatus::INCOMPLETE;
          }

          // Forçamos o estado para finalizada
          $this->setState(new Finished());
        }

        // Indicamos que ao final devemos gravar a viagem,
        // independente de a mesma estar ou não completa
        $storeRoadTrip = true;
        $this->out("<b>Viagem encerrada</b>");

        break;
      default:
        // Não foi possível determinar o evento em função dos dados
        // do comando recebido do teclado
        throw new InvalidArgumentException("Erro ao processar os "
          . "dados de posicionamento do veículo placa '{$plate}' no "
          . "registro {$registreID}: Comando do teclado "
          . $event->manufacturer . " não reconhecido."
        );

        break;
    }

    // Adicionamos o evento
    $this->appendEvent($event);

    // Disparamos o evento de gravação dos dados de viagem, se
    // necessário
    if ($storeRoadTrip) {
      // Concluímos a viagem
      $this->endRoadTrip();
    }
  }

  /**
   * Encerra qualquer viagem que esteja em aberto ao final do
   * processamento dos registros de eventos.
   */
  public function close(): void
  {
    // Verificamos se temos dados processados
    if (count($this->events) > 0) {
      // Temos um ou mais eventos, então devemos considerar que a viagem
      // atual está incompleta
      $this->out("Finalizando viagem incompleta");
      $this->enforcementStatus = RoadTripStatus::INCOMPLETE;

      // Concluímos a viagem
      $this->endRoadTrip();
    }
  }

  /**
   * Adiciona um evento à lista de eventos. Antes de adicionar, calcula
   * a duração do evento.
   *
   * @param EventEntry $event
   *   O evento à ser adicionado
   */
  protected function appendEvent(EventEntry $event): void
  {
    if ($event->ignore === false) {
      // Na ocorrência de alguns eventos, precisamos realizar o cálculo
      // de duração que nos permite, posteriormente, determinar os
      // diversos tempos de viagem. Por exemplo, quando ocorre uma
      // parada durante a viagem, armazenamos a posição deste evento. No
      // reinício precisamos disparar o calculo que leva em consideração
      // justamente a diferença de tempo entre estes dois eventos. Este
      // tipo de lógica é repetido também entre os demais eventos, nos
      // permitindo determinar os tempos de direção, de parada para
      // descanso, parada em espera, etc. Neste ponto, analisamos se o
      // evento que vamos adicionar é um evento de fechamento de um
      // período (de direção ou parada)
      switch ($event->type) {
        case EventType::STOP:
        case EventType::RESTART:
        case EventType::FINISH:
          // Estamos fechando um período. Então verificamos se temos o
          // respectivo evento inicial deste período
          if (array_key_exists($this->lastEventIndex, $this->events)) {
            // Calculamos a duração entre o evento inicial e este evento
            // e armazenamos o resultado no evento inicial
            $this
              ->events[ $this->lastEventIndex ]
              ->calculateDurationUntil(
                  $event->eventDate
                )
            ;

            // Adicionamos este tempo aos tempos de viagem
            $stopType = $this->events[ $this->lastEventIndex ]->stopType;
            $duration = $this->events[ $this->lastEventIndex ]->duration;
            $this->tripTimes[ $stopType ] += $duration;
          }
          
          break;
        default:
          // Não precisa calcular
          break;
      }
    }

    // Adicionamos o evento
    $this->events[] = $event;

    if ( ($event->ignore === false)
      && (in_array($event->type, $this->eventsToCalculate)) ) {
      // Armazenamos o índice do evento para calcular a duração
      $this->lastEventIndex = count($this->events) - 1;
    }
  }

  /**
   * Monta os dados da viagem sendo processada e os retorna.
   *
   * @return array
   */
  protected function buildRoadTripData(): array
  {
    // Verifica se a viagem está completa, mesmo que parcialmente
    $hrSingleStyle = '"border: 0; border-top: 1px dotted #004c8c;"';
    $hrDoubleStyle = '"border: 0; border-top: 3px double #004c8c;"';
    if ($this->enforcementStatus !== RoadTripStatus::INCOMPLETE) {
      // Calculamos a duração da viagem
      $last = array_key_last($this->events);
      $durationInSeconds = $this->events[0]->eventDate->diffInSeconds(
        $this->events[$last]->eventDate
      );
    } else {
      // Consideramos a duração como indeterminada
      $durationInSeconds = 0;
    }

    if ($this->debug) {
      // Geramos a impressão dos dados da viagem
      $this->simpleOut("<hr style={$hrDoubleStyle}>");
      $this->out("<b style={$this->valueStyle}>",
        "Montando viagem para o motorista ", $this->currentDriverID,
        " no veículo placa ", $this->currentPlate, ":</b>"
      );
      $this->out("Duração da viagem: <b>",
        $this->formatDuration($durationInSeconds), "</b>"
      );
      $this->out("Condição da viagem: <b>",
        RoadTripStatus::toString($this->enforcementStatus), "</b>"
      );
      $this->simpleOut("<hr style={$hrSingleStyle}>");
    }

    // Recuperamos as informações da viagem
    if ($this->buildAsEventList) {
      // Recupera a informação dos eventos na forma de uma lista
      $roadTripData = [];
      foreach ($this->events as $pos => $event) {
        $this->out("<b>Evento {$pos}:</b>");

        // Converte individualmente cada evento para uma matriz
        $eventData = $event->toArray();

        // Formatamos a data e hora separados
        $eventData['day'] = $eventData['datetime']->format('d/m/Y');
        $eventData['dayOfWeek'] = $this->dayOfWeekName[
          $eventData['datetime']->dayOfWeek
        ];
        $eventData['time'] = $eventData['datetime']->format('H:i:s');

        // Formatamos o tipo do evento
        $eventData['typeofevent'] = ($eventData['stopType'] > 0)
          ? StopType::readable($eventData['stopType'])
          : EventType::readable($eventData['eventType'])
        ;

        // Determina o tempo do evento
        if ($eventData['eventType'] === EventType::MESSAGE) {
          // Eventos de mensagem não possuem duração
          $eventData['duration'] = '';
        } elseif ($eventData['eventType'] === EventType::FINISH) {
          // A duração é a duração da viagem
          $eventData['duration'] = $this->formatDuration($durationInSeconds);
        } elseif ($eventData['ignore']) {
          // Eventos a serem ignorados não possuem duração
          $eventData['duration'] = '';
        } else {
          if (($eventData['eventType'] === EventType::RESTART)
              && ($eventData['autofixed'])) {
            $eventData['duration'] = '--:--:--';
          } else {
            // Colocamos a própria duração do evento
            $eventData['duration'] = $this->formatDuration($eventData['duration']);
          }
        }

        // Acrescentamos as informações adicionais
        $eventData['driverID'] = $this->currentDriverID;
        $eventData['plate'] = $this->currentPlate;
        $eventData['status'] = RoadTripStatus::toString(
          $this->enforcementStatus
        );

        if ($this->debug) {
          // Fazemos a saída do evento
          foreach ($eventData as $key => $value) {
            $this->out("<span style={$this->keyStyle}>", $key, ": ",
              "</span>", "<span style={$this->valueStyle}>",
              $value, "</span>"
            );
          }

          if ($pos === array_key_last($this->events)) {
            $this->simpleOut("<hr style={$hrDoubleStyle}>");
          } else {
            $this->simpleOut("<hr style={$hrSingleStyle}>");
          }
        }

        $roadTripData[] = $eventData;
      }
    } else {
      // Recupera a informação dos eventos e monta uma matriz com todos
      // os dados da viagem

      // Recupera a informação dos tempos de viagem
      $tripTimes = [];
      $this->out("<b>Tempos da viagem:</b>");
      foreach ($this->tripTimes as $stopType => $duration) {
        // Converte individualmente cada tempo para uma matriz
        if ($stopType > 0) {
          $name = StopType::toString($stopType);
          $readableName = StopType::readable($stopType);
        } else {
          $name = 'driving';
          $readableName = 'Condução';
        }

        $tripTimes[$name] = $duration;
        $this->out($readableName, ": ",
          "<span style={$this->valueStyle}>",
          $this->formatDuration($duration), "</span>"
        );
      }
      $this->simpleOut("<hr style={$hrSingleStyle}>");

      // Recupera a informação dos eventos de viagem
      $events = [];
      foreach ($this->events as $pos => $event) {
        // Converte individualmente cada evento para uma matriz
        $this->out("<b>Evento {$pos}:</b>");
        $eventData = $event->toArray();
        $events[] = $eventData;
        foreach ($eventData as $key => $value) {
          if ($key === 'duration') {
            $value = $this->formatDuration($value);
          }

          $this->out("<span style={$this->keyStyle}>", $key, ": ",
            "</span>", "<span style={$this->valueStyle}>",
            $value, "</span>"
          );
        }

        if ($this->debug) {
          if ($pos === array_key_last($this->events)) {
            $this->simpleOut("<hr style={$hrDoubleStyle}>");
          } else {
            $this->simpleOut("<hr style={$hrSingleStyle}>");
          }
        }
      }

      // Monta a informação da viagem
      $roadTripData = [
        'driverID'    => $this->currentDriverID,
        'plate'       => $this->currentPlate,
        'status'      => $this->enforcementStatus,
        'duration'    => $durationInSeconds,
        'times'       => $tripTimes,
        'events'      => $events
      ];
    }

    return $roadTripData;
  }

  /**
   * Concluí a viagem atual.
   */
  protected function endRoadTrip(): void
  {
    // Recuperamos os dados da viagem
    $roadTripData = $this->buildRoadTripData();

    // Disparamos o evento de gravação dos dados de viagem
    if (isset($this->onRoadTripCompleted)) {
      call_user_func($this->onRoadTripCompleted, $roadTripData);
    }

    if ($this->buildAsEventList) {
      // Adicionamos estes novos eventos ao final dos dados da viagem
      foreach ($roadTripData as $trip) {
        // Adicionamos este evento de viagem à relação de viagens
        $this->trips[] = $trip;
      }
    } else {
      // Adicionamos esta viagem à relação de viagens
      $this->trips[] = $roadTripData;
    }

    // Limpamos os dados para iniciar uma nova viagem
    $this->clearRoadTripData();
  }

  /**
   * Limpa os dados de viagem.
   */
  protected function clearRoadTripData(): void
  {
    $this->out("Zerando informações de eventos de viagem");

    // Limpamos todos os eventos
    $this->events = [];

    // Zeramos os tempos de viagem
    array_walk($this->tripTimes, function(&$value) { $value = 0; });

    // Marcamos que o estado da viagem está desconhecido pois não temos
    // nenhum dado processado
    $this->enforcementStatus = RoadTripStatus::UNKNOWN;
    
    // Indica que neste momento o estado de nossa viagem é desconhecido
    $this->setState(new Unknown());

    $this->lastEventIndex = null;
  }

  /**
   * Obtém as informações das viagens processadas
   *
   * @return array
   *   Uma matriz com as informações de viagens processadas
   */
  public function getRoadTrips(): array
  {
    return $this->trips;
  }
}
