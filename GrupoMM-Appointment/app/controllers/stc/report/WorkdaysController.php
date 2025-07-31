<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * O controlador do relatório que permite visualizar as informações de
 * jornadas de trabalho. Permite acompanhar todos os eventos de um 
 * dia de trabalho através das trocas de mensagens pelo sistema de
 * controle de jornada de trabalho acoplado ao rastreador.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Report;

use App\Models\STC\Customer;
use App\Models\STC\Driver;
use App\Models\STC\Position;
use App\Models\STC\JourneyPerDay;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Core\Helpers\GraphicsTrait;
use Core\RoadTrip\Keyboard\SGBRAS;
use Core\RoadTrip\RoadTrip;
use Core\RoadTrip\WorkedDays\WorkedDayAnalyzer;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class WorkdaysController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos. Necessário
   * para se obter a logomarca do contratante no PDF
   */
  use HandleFileTrait;

  /**
   * Os métodos para formatação
   */
  use FormatterTrait;

  /**
   * Os métodos para renderização gráfica
   */
  use GraphicsTrait;

  /**
   * A fonte de letra usada nos relatórios em PDF
   *
   * @var string
   */
  protected $font = 'DejaVuSansCondensed';

  /**
   * O tamanho (altura) do texto
   *
   * @var integer
   */
  protected $textSize = 10;

  /**
   * Flag que nos permite limitar os textos à uma única linha
   *
   * @var boolean
   */
  protected $limitTextToSingleLine = false;

  /**
   * Exibe a página inicial do relatório de detalhamento das jornadas
   * de trabalho em função das informações de jornadas executadas.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Relatório', '');
    $this->breadcrumb->push('Jornadas de trabalho',
      $this->path('STC\Report\Workdays')
    );
    
    // Registra o acesso
    $this->info("Acesso ao relatório de detalhamento das jornadas "
      . "de trabalho."
    );

    // Recupera os dados da sessão
    $start = Carbon::now()->locale('pt_BR')->sub('1 month');
    $end   = Carbon::now()->locale('pt_BR')->sub('1 day');
    $workday = $this->session->get('workday',
      [ 'start' => $start->format('d/m/Y'),
        'end' => $end->format('d/m/Y'),
        'customer' => [
          'id' => 0,
          'name'  => ''
        ],
        'driver' => [
          'id' => 0,
          'name'  => ''
        ]
      ])
    ;

    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid > 5) {
      // Recupera a informação do contratante
      $contractor = $this->authorization->getContractor();

      // Recuperamos o código do cliente
      $customerID = $this->authorization->getUser()->entityid;

      // Recuperamos os dados do cliente no sistema STC
      $customer = Customer::where('contractorid', '=', $contractor->id)
        ->where('customerid', '=', $customerID)
        ->get([
            'clientid AS id',
            'name'
          ])
        ->first()
      ;
      
      // Força a seleção dos dados deste cliente
      $workday['customer'] = [
        'id'   => $customer->id,
        'name' => $customer->name
      ];
    }

    // Verifica se as datas estão em branco
    if (empty($workday['start']) || empty($workday['end'])) {
      $workday['start'] = $start->format('d/m/Y');
      $workday['end'] = $end->format('d/m/Y');
    }
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/report/workdays/workdays.twig',
      [ 'workday' => $workday ])
    ;
  }
  
  /**
   * Recupera a relação das jornadas de trabalho em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(Request $request, Response $response)
  {
    $this->debug("Acesso à relação de jornadas de trabalho.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];
    
    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];
    
    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem
    
    // O campo de pesquisa selecionado
    $startDate    = $postParams['startDate'];
    $endDate      = $postParams['endDate'];
    $clientID     = $postParams['clientID'];
    $customerName = $postParams['customerName'];
    $driverID     = $postParams['driverID'];
    $driverName   = $postParams['driverName'];
    
    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid > 5) {
      // Recuperamos o código do cliente
      $customerID = $this->authorization->getUser()->entityid;

      // Recuperamos os dados do cliente no sistema STC
      $customer = Customer::where('contractorid', '=', $contractor->id)
        ->where('customerid', '=', $customerID)
        ->get([
            'clientid AS id',
            'name'
          ])
        ->first()
      ;
      
      // Força a seleção dos dados deste cliente
      $clientID = $customer->id;
      $customerName =$customer->name;
    }

    if (empty($startDate) || empty($endDate)) {
      $error = 'Informe um período válido para este relatório';

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [ ],
            'error' => $error
          ])
      ;
    }

    // Registra a solicitação
    $this->info("Solicitado dados de jornadas de trabalho de [{id}] "
      . "{name} da empresa [{clientID}] {customerName} do período de "
      . "{start} até {end}.",
      [ 'id' => $driverID,
        'name' => $driverName,
        'clientID' => $clientID,
        'customerName' => $customerName,
        'start' => $startDate,
        'end'  => $endDate ]
    );

    // Seta os valores da última pesquisa na sessão
    $this->session->set('workday',
      [ 'start' => $startDate,
        'end'   => $endDate,
        'customer' => [
          'id'   => $clientID,
          'name' => $customerName
        ],
        'driver' => [
          'id'   => $driverID,
          'name' => $driverName
        ],
      ]
    );

    if ( ($clientID === 0) || ($driverID === 0) ) {
      $error = 'Selecione um cliente e um motorista primeiramente.';

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [ ],
            'error' => $error
          ])
      ;
    }

    // Converte as datas para o formato correto
    $startDate = $this->toSQLDate($startDate);
    $endDate   = $this->toSQLDate($endDate);

    // Determina qual(is) a(s) jornada(s) de trabalho este
    // motorista deve cumprir dentro do período informado
    $sql = "SELECT journeyid,
                   name,
                   begginingat,
                   startdaytime,
                   enddaytime,
                   startnighttime,
                   endnighttime,
                   computeovertime,
                   discountworkedlesshours
              FROM stc.getJourneysForDriveOnPeriod({$contractor->id},
                {$clientID}, {$driverID}, '{$startDate}'::date,
                '{$endDate}'::date);"
    ;
    $journeysForDrive = $this->DB->select($sql);
    $computeOvertime = false;

    // Verifica se temos ao menos uma jornada de trabalho a ser
    // cumprida
    if (is_null($journeysForDrive)) {
      $error = 'Nenhuma jornada de trabalho definida para o '
        . 'cliente e/ou motorista.'
      ;

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [ ],
            'error' => $error
          ])
      ;
    }

    // Para cada jornada de trabalho, recuperamos as informações
    // de horas trabalhadas por dia
    $driverJourneys = [ ];
    foreach ($journeysForDrive as $journeyForDrive) {
      $journeyData = [
        'startdaytime' => $journeyForDrive->startdaytime,
        'enddaytime' => $journeyForDrive->enddaytime,
        'startnighttime' => $journeyForDrive->startnighttime,
        'endnighttime' => $journeyForDrive->endnighttime,
        'discountWorkedLessHours' => $journeyForDrive->discountworkedlesshours
      ];
      $journeysPerDay = JourneyPerDay::where('contractorid', '=',
            $contractor->id
          )
        ->where('clientid', '=', $clientID)
        ->where('journeyid', '=', $journeyForDrive->journeyid)
        ->orderBy('dayofweek', 'DESC')
        ->get([
            'dayofweek',
            'seconds'
          ])
      ;

      $days = [ ];
      foreach ($journeysPerDay as $journeyPerDay) {
        $days[$journeyPerDay->dayofweek] = $journeyPerDay->seconds;
      }

      $journeyData['days'] = $days;
      $driverJourneys[$journeyForDrive->begginingat] = $journeyData;
      $computeOvertime = $journeyForDrive->computeovertime;
    }

    // Precisamos acrescentar D+1 no final para pegar o conteúdo
    // completo
    $dt = Carbon::createFromFormat('Y-m-d H:i:s',
      $endDate . " 00:00:00")->locale('pt_BR')
    ;
    $endDate   = $dt->addDay()->format('Y-m-d');

    if ( ($clientID > 0) && ($driverID > 0) ) {
      try
      {
        // Recupera as informações de posicionamentos
        $positions = Position::join('stc.vehicles',
            function($join) {
              $join->on('positions.plate', '=', 'vehicles.plate');
              $join->on('positions.contractorid', '=',
                'vehicles.contractorid'
              );
            })
          ->where('positions.rs232', '<>', '')
          ->whereBetween('positions.eventdate', [ $startDate, $endDate])
          ->where('vehicles.clientid', '=', $clientID)
          ->where('positions.driverid', '=', $driverID)
          ->where('positions.contractorid', '=', $contractor->id)
          ->orderBy('positions.eventdate')
          ->get([
              'positions.positionid AS id',
              'positions.eventdate',
              $this->DB->raw("to_char(positions.eventDate, 'HH24:MI') "
                . "AS hour"
              ),
              'positions.plate',
              'positions.driverid',
              'positions.drivername',
              'positions.rs232',
              'positions.address'
           ])
        ;
        $this->debug("Recuperada(s) {amount} posições.",
          [ 'amount' => count($positions) ]
        );

        if (count($positions) > 0) {
          // Iniciamos o adaptador que fará a interpretação dos comandos
          // oriundos do teclado no padrão SGBRAS
          $keyboard = new SGBRAS();

          // Criamos o analisador de viagens, que irá separar as
          // informações de cada viagem com os seus respectivos eventos,
          // e analisando a situação da mesma em relação ao cumprimento
          $roadtrip = new RoadTrip($keyboard);

          // Criamos o calculador de horas trabalhadas, que analisará as
          // viagens e irá separar as informações de horas trabalhadas
          $workedDayAnalyzer = new WorkedDayAnalyzer($roadtrip);
          $workedDayAnalyzer->setWorkingHours($driverJourneys);

          // Processa os dados recuperados
          foreach ($positions AS $row => $position) {
            // Recuperamos a data/hora do evento
            $eventDate = Carbon::createFromFormat('Y-m-d H:i:s',
              $position->eventdate)->locale('pt_BR')
            ;

            // Cada registro é analisado e adicionado ou descartado
            // levando-se em consideração as sequências em que os mesmos
            // foram executados, de forma a permitir obtermos as
            // informações de viagens executadas por cada motorista.
            // Estas informações então são passadas para o calculador de
            // horas trabalhadas que irá separar os valores por dia de
            // trabalho
            $workedDayAnalyzer->parse($position->id,
              $position->driverid, $position->plate, $eventDate,
              $position->rs232, $position->address
            );
          }

          // Ao final, garantimos que qualquer viagem ainda não
          // encerrada seja computada
          $workedDayAnalyzer->close();

          // Agora recuperamos as informações de dias trabalhados
          $workedDays = $workedDayAnalyzer->getWorkedDays();
          $this->debug("Recuperado(s) {days} dia(s) trabalhado(s).",
            [ 'days' => count($workedDays) ]
          );

          $error = null;

          // Processamos os dados convertendo numa lista de eventos,
          // pois cada jornada é uma matriz com os eventos ocorridos
          $events = [ ];
          $id = 0;
          foreach ($workedDays as $workedDay) {
            foreach ($workedDay['events'] as $row) {
              $event = [
                "id"            => ++$id,
                "date"          => $workedDay['day'],
                "dayOfWeek"     => $workedDay['dayOfWeek'],
                "worked"        => $workedDay['worked'],
                "overtime"      => $workedDay['overtime'],
                "dayshift"      => $workedDay['dayshift'],
                "nightshift"    => $workedDay['nightshift'],
                "overtimeLabel" => $computeOvertime
                  ? 'Extra'
                  : 'Banco de horas',
                "time"          => $row['time'],
                "eventType"     => $row['typeName'],
                "plate"         => $row['plate'],
                "location"      => $row['location'],
                "duration"      => $row['duration']
              ];

              $events[] = (object) $event;
            }
          }

          // Determina o total de registros de eventos
          $totalOfRegisters = count($events);
          $this->debug("Gerado(s) {amount} eventos(s).",
            [ 'amount' => $totalOfRegisters ]
          );

          // Paginamos os dados de eventos
          $eventsPaginated = array_slice($events, $start, $length);
          $this->debug("Dos {total} eventos, retornamos à partir de "
            . "{start} com {length} de tamanho.",
            [ 'total' => $totalOfRegisters,
              'start' => $start,
              'length' => $length ]
          );
          
          if (count($eventsPaginated) > 0) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'draw' => $draw,
                  'recordsTotal' => $totalOfRegisters,
                  'recordsFiltered' => $totalOfRegisters,
                  'data' => $eventsPaginated
                ])
            ;
          } else {
            $error = "Não temos dados de jornadas de trabalho.";
          }
        } else {
          $error = "Não temos posicionamentos para o período indicado.";
        }
      }
      catch(InvalidArgumentException $exception) {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno no banco de dados: {error}.",
          [ 'module' => 'jornadas de trabalho',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "jornadas de trabalho. Erro interno no banco de dados."
        ;
      }
      catch(QueryException $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno no banco de dados: {error}.",
          [ 'module' => 'jornadas de trabalho',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "jornadas de trabalho. Erro interno no banco de dados."
        ;
      }
      catch(Exception $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno: {error}.",
          [ 'module' => 'jornadas de trabalho',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "jornadas de trabalho. Erro interno."
        ;
      }
    } else {
      // Não retornamos dados
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [ ]
          ])
      ;
    }
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [ ],
          'error' => $error
        ])
    ;
  }

  /**
   * Gera um PDF para impressão das informações de jornadas de trabalho.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function getPDF(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à geração de PDF com o relatório de "
      . "jornadas de trabalho."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do relatório
    $args = $request->getQueryParams();
    $startDate = $args['startDate'];
    $endDate   = $args['endDate'];
    $clientID  = $args['clientID'];
    $driverID  = $args['driverID'];
    
    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid > 5) {
      // Recuperamos o código do cliente
      $customerID = $this->authorization->getUser()->entityid;

      // Recuperamos os dados do cliente no sistema STC
      $customer = Customer::where('contractorid', '=', $contractor->id)
        ->where('customerid', '=', $customerID)
        ->get([
            'clientid AS id',
            'name'
          ])
        ->first()
      ;
      
      // Força a seleção dos dados deste cliente
      $clientID = $customer->id;
    }

    // Converte as datas para o formato correto
    $startDate = $this->toSQLDate($startDate);
    $endDate   = $this->toSQLDate($endDate);

    // Precisamos acrescentar D+1 no final para pegar o conteúdo
    // completo
    $dt = Carbon::createFromFormat('Y-m-d H:i:s',
      $endDate . " 00:00:00")->locale('pt_BR')
    ;
    $endDate   = $dt->addDay()->format('Y-m-d');

    // Inicialmente definimos que as jornadas serão tratadas como banco
    // de horas. Conforme a jornada é determinada, este valor é ajustado
    $computeOvertime = false;

    // Verifica se temos as informações necessárias
    if (empty($startDate) || empty($endDate)) {
      $error = 'Informe um período válido para este relatório';
    } else {
      if ( ($clientID > 0) && ($driverID > 0) ) {
        try {
          // Recupera os dados do motorista
          $driver = Driver::join('stc.customers',
              function($join) {
                $join->on('drivers.clientid', '=', 'customers.clientid');
                $join->on('drivers.contractorid', '=',
                  'customers.contractorid'
                );
              })
            ->where('drivers.contractorid', '=', $contractor->id)
            ->where('drivers.clientid', '=', $clientID)
            ->where('drivers.driverid', '=', $driverID)
            ->get([
                'drivers.driverid AS id',
                'drivers.name',
                'drivers.occupation',
                'customers.name AS customername',
                $this->DB->raw("CASE "
                  . "WHEN customerismyemployer THEN customers.name "
                  . "ELSE drivers.employername "
                  . "END AS employername"
                )
              ])
            ->first()
          ;

          // Seta os valores da última pesquisa na sessão
          $this->session->set('workday',
            [ 'start' => $args['startDate'],
              'end'   => $args['endDate'],
              'customer' => [
                'id'   => $clientID,
                'name' => $driver->customername
              ],
              'driver' => [
                'id'   => $driverID,
                'name' => $driver->name
              ],
            ]
          );

          // Registra a solicitação
          $this->info("Solicitado PDF com os dados de jornadas de "
            . "trabalho de [{id}] {name} da empresa [{clientID}] "
            . "{customerName} do período de {start} até {end}.",
            [ 'id' => $driverID,
              'name' => $driver->name,
              'clientID' => $clientID,
              'customerName' => $driver->customername,
              'start' => $args['startDate'],
              'end'  => $args['endDate'] ]
          );

          // Determina qual(is) a(s) jornada(s) de trabalho este
          // motorista deve cumprir dentro do período informado
          $sql = "SELECT journeyid,
                         name,
                         begginingat,
                         startdaytime,
                         enddaytime,
                         startnighttime,
                         endnighttime,
                         computeovertime,
                         discountworkedlesshours
                    FROM stc.getJourneysForDriveOnPeriod({$contractor->id},
                      {$clientID}, {$driverID}, '{$startDate}'::date,
                      '{$endDate}'::date);"
          ;
          $journeysForDrive = $this->DB->select($sql);

          // Verifica se temos ao menos uma jornada de trabalho a ser
          // cumprida
          if (is_null($journeysForDrive)) {
            $error = 'Nenhuma jornada de trabalho definida para o '
              . 'cliente e/ou motorista.'
            ;
          } else {
            // Para cada jornada de trabalho, recuperamos as informações
            // de horas trabalhadas por dia
            $driverJourneys = [ ];
            foreach ($journeysForDrive as $journeyForDrive) {
              $journeyData = [
                'startdaytime' => $journeyForDrive->startdaytime,
                'enddaytime' => $journeyForDrive->enddaytime,
                'startnighttime' => $journeyForDrive->startnighttime,
                'endnighttime' => $journeyForDrive->endnighttime,
                'discountWorkedLessHours' => $journeyForDrive->discountworkedlesshours
              ];
              $journeysPerDay = JourneyPerDay::where('contractorid',
                    '=', $contractor->id
                  )
                ->where('clientid', '=', $clientID)
                ->where('journeyid', '=', $journeyForDrive->journeyid)
                ->orderBy('dayofweek', 'DESC')
                ->get([
                    'dayofweek',
                    'seconds'
                  ])
              ;

              $days = [ ];
              foreach ($journeysPerDay as $journeyPerDay) {
                $days[$journeyPerDay->dayofweek] = $journeyPerDay->seconds;
              }

              $journeyData['days'] = $days;
              $driverJourneys[$journeyForDrive->begginingat] = $journeyData;
              $computeOvertime = $journeyForDrive->computeovertime;
            }

            // Recupera as informações de posicionamentos
            $positions = Position::join('stc.vehicles',
                function($join) {
                  $join->on('positions.plate', '=', 'vehicles.plate');
                  $join->on('positions.contractorid', '=',
                    'vehicles.contractorid'
                  );
                })
              ->where('positions.rs232', '<>', '')
              ->whereBetween('positions.eventdate', [ $startDate, $endDate])
              ->where('vehicles.clientid', '=', $clientID)
              ->where('positions.driverid', '=', $driverID)
              ->where('positions.contractorid', '=', $contractor->id)
              ->orderBy('positions.eventdate')
              ->get([
                  'positions.positionid AS id',
                  'positions.eventdate',
                  $this->DB->raw("to_char(positions.eventDate, "
                    . "'HH24:MI') AS hour"
                  ),
                  'positions.plate',
                  'positions.driverid',
                  'positions.drivername',
                  'positions.rs232',
                  'positions.address'
               ])
            ;

            $this->debug("Recuperada(s) {amount} posições.",
              [ 'amount' => count($positions) ]
            );

            if (count($positions) > 0) {
              // Iniciamos o adaptador que fará a interpretação dos
              // comandos oriundos do teclado no padrão SGBRAS
              $keyboard = new SGBRAS();

              // Criamos o analisador de viagens, que irá separar as
              // informações de cada viagem com os seus respectivos
              // eventos, e analisando a situação da mesma em relação ao
              // cumprimento
              $roadtrip = new RoadTrip($keyboard);

              // Criamos o calculador de horas trabalhadas, que
              // analisará as viagens e irá separar as informações de
              // horas trabalhadas
              $workedDayAnalyzer = new WorkedDayAnalyzer($roadtrip);
              $workedDayAnalyzer->setWorkingHours($driverJourneys);

              // Processa os dados recuperados
              foreach ($positions AS $position) {
                // Recuperamos a data/hora do evento
                $eventDate = Carbon::createFromFormat('Y-m-d H:i:s',
                  $position->eventdate)->locale('pt_BR')
                ;

                if ($this->limitTextToSingleLine) {
                  // Limita o endereço para que o mesmo seja exibido em
                  // uma única linha
                  $address = $this->limitWidth($position->address, 520,
                    $this->textSize, $this->font
                  );
                } else {
                  $address = $position->address;
                }

                // Cada registro é analisado e adicionado ou descartado
                // levando-se em consideração as sequências em que os
                // mesmos foram executados, de forma a permitir obtermos
                // as informações de viagens executadas por cada
                // motorista. Estas informações então são passadas para
                // o calculador de horas trabalhadas que irá separar os
                // valores por dia de trabalho
                $workedDayAnalyzer->parse($position->id,
                  $position->driverid, $position->plate, $eventDate,
                  $position->rs232, $address
                );
              }

              // Ao final, garantimos que qualquer viagem ainda não
              // encerrada seja computada
              $workedDayAnalyzer->close();

              // Agora recuperamos as informações de dias trabalhados
              $workedDays = $workedDayAnalyzer->getWorkedDays();
              $totalizers = $workedDayAnalyzer->getTotalizers();
              $this->debug("Recuperado(s) {days} dia(s) trabalhado(s).",
                [ 'days' => count($workedDays) ]
              );

              $error = null;
            } else {
              $error = 'Nenhum registro retornado.';
            }
          }
        }
        catch(InvalidArgumentException $exception) {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno no banco de dados: {error}.",
            [ 'module' => 'jornadas executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "jornadas executadas. Erro de argumento inválido."
          ;
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno: {error}.",
            [ 'module' => 'jornadas executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "jornadas executadas. Erro interno."
          ;
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno no banco de dados: {error}.",
            [ 'module' => 'jornadas executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "jornadas executadas. Erro interno no banco de dados."
          ;
        }
      } else {
        $error = "Selecione o cliente, funcionário e período desejado";
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Jornada de trabalho";
    $PDFFileName = "Workdays_{$clientID}_{$driverID}_from_{$startDate}_to_{$endDate}.pdf";
    $page = $this->renderPDF('stc/report/workdays/PDFworkdays.twig',
      [
        'startDate'       => $args['startDate'],
        'endDate'         => $args['endDate'],
        'driver'          => $driver,
        'workedDays'      => $workedDays,
        'totalizers'      => $totalizers,
        'computeOvertime' => $computeOvertime,
        'error'           => $error
      ])
    ;
    $logo   = $this->getContractorLogo($contractor->uuid, 'normal');
    $header = $this->renderPDFHeader($title, $logo);
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Landscape'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion=true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Jornadas executadas');
    $mpdf->SetCreator('TrackerERP');

    // Define os cabeçalhos e rodapés
    $mpdf->SetHTMLHeader($header);
    $mpdf->SetHTMLFooter($footer);

    // Seta modo tela cheia
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->showImageErrors = false;
    $mpdf->debug = false;

    // Inclui o conteúdo
    $mpdf->WriteHTML($page);

    // Envia o PDF para o browser no modo Inline
    $stream = fopen('php://memory','r+');
    ob_start();
    $mpdf->Output($PDFFileName,'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }
}
