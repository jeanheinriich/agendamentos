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
 * viagens realizadas. Permite acompanhar todos os eventos de cada
 * viagem através das trocas de mensagens pelo sistema de controle da
 * jornada de trabalho acoplado ao rastreador.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Report;

use App\Models\STC\Customer;
use App\Models\STC\Driver;
use App\Models\STC\Position;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Core\Helpers\GraphicsTrait;
use Core\RoadTrip\Keyboard\SGBRAS;
use Core\RoadTrip\RoadTrip;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class RoadTripsController
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
   * Exibe a página inicial do relatório de detalhamento das viagens
   * executadas.
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
    $this->breadcrumb->push('Viagens',
      $this->path('STC\Report\RoadTrips')
    );
    
    // Registra o acesso
    $this->info("Acesso ao relatório de detalhamento das viagens "
      . "executadas."
    );

    // Recupera os dados da sessão
    $start = Carbon::now()->locale('pt_BR')->sub('1 month');
    $end   = Carbon::now()->locale('pt_BR')->sub('1 day');
    $roadtrip = $this->session->get('roadtrip',
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
      $roadtrip['customer'] = [
        'id'   => $customer->id,
        'name' => $customer->name
      ];
    }

    // Verifica se as datas estão em branco
    if (empty($roadtrip['start']) || empty($roadtrip['end'])) {
      $roadtrip['start'] = $start->format('d/m/Y');
      $roadtrip['end'] = $end->format('d/m/Y');
    }
    
    // Renderiza a página
    return  $this->render($request, $response,
      'stc/report/roadtrips/roadtrips.twig',
      [ 'roadtrip' => $roadtrip ])
    ;
  }
  
  /**
   * Recupera a relação das viagens executadas em formato JSON.
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
    $this->debug("Acesso à relação de viagens executadas.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];
    
    // As definições das colunas
    $columns = $postParams['columns'];
    
    // O ordenamento, onde:
    //   column: id da coluna
    //      dir: direção
    $order    = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);
    
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
    $this->info("Solicitado as viagens executadas do motorista [{id}] "
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
    $this->session->set('roadtrip',
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
          // informações de cada viagem com os seus respectivos
          // eventos, e analisando a situação da mesma em relação ao
          // cumprimento
          $roadtrip = new RoadTrip($keyboard);
          $roadtrip->setBuildAsEventList();

          // Processa os dados recuperados
          foreach ($positions AS $row => $position) {
            // Recuperamos a data/hora do evento
            $eventDate = Carbon::createFromFormat('Y-m-d H:i:s',
              $position->eventdate)->locale('pt_BR')
            ;

            // Cada registro é analisado e adicionado ou descartado
            // levando-se em consideração as sequências em que os mesmos
            // foram executados, de forma a permitir obtermos as
            // informações de viagens executadas por cada motorista
            $roadtrip->parse($position->id, $position->driverid,
              $position->plate, $eventDate, $position->rs232,
              $position->address
            );
          }

          // Ao final, garantimos que qualquer viagem ainda não
          // encerrada seja computada
          $roadtrip->close();

          // Agora recuperamos os eventos das viagens executadas
          $events = $roadtrip->getRoadTrips();
          $totalOfRegisters = count($events);
          $this->debug("Recuperado(s) {amount} eventos de viagem.",
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
            $error = "Não temos eventos de viagens executadas.";
          }
        } else {
          $error = "Não temos posicionamentos para o período indicado.";
        }
      }
      catch(InvalidArgumentException $exception) {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno no banco de dados: {error}.",
          [ 'module' => 'viagens executadas',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "viagens executadas. Erro interno no banco de dados."
        ;
      }
      catch(QueryException $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno no banco de dados: {error}.",
          [ 'module' => 'viagens executadas',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "viagens executadas. Erro interno no banco de dados."
        ;
      }
      catch(Exception $exception)
      {
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. Erro interno: {error}.",
          [ 'module' => 'viagens executadas',
            'error'  => $exception->getMessage() ]
        );

        $error = "Não foi possível recuperar as informações de "
          . "viagens executadas. Erro interno."
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
   * Gera um PDF para impressão das informações de viagens executadas.
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
      . "viagens executadas."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do relatório
    $args = $request->getQueryParams();
    $startDate = $args['startDate'];
    $endDate   = $args['endDate'];
    $clientID  = $args['clientID'];
    $driverID  = $args['driverID'];

    // Inicializa as variáveis
    $events = [ ];
    
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

    // Converte as datas para o formato correto
    $startDate = $this->toSQLDate($startDate);
    $endDate   = $this->toSQLDate($endDate);

    // Precisamos acrescentar D+1 no final para pegar o conteúdo
    // completo
    $dt = Carbon::createFromFormat('Y-m-d H:i:s',
      $endDate . " 00:00:00")->locale('pt_BR')
    ;
    $endDate   = $dt->addDay()->format('Y-m-d');

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
                $this->DB->raw(''
                  . 'CASE '
                  . '  WHEN customerismyemployer THEN customers.name '
                  . '  ELSE drivers.employername '
                  . 'END AS employername'
                )
              ])
            ->first()
          ;

          // Seta os valores da última pesquisa na sessão
          $this->session->set('roadtrip',
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
            ])
          ;
          // Registra a solicitação
          $this->info("Solicitado PDF com as viagens executadas do "
            . "motorista [{id}] {name} da empresa [{clientID}] "
            . "{customerName} do período de {start} até {end}.",
            [ 'id' => $driverID,
              'name' => $driver->name,
              'clientID' => $clientID,
              'customerName' => $driver->customername,
              'start' => $args['startDate'],
              'end'  => $args['endDate'] ]
          );

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
            $roadtrip->setBuildAsEventList();

            // Processa os dados recuperados
            foreach ($positions AS $row => $position) {
              // Recuperamos a data/hora do evento
              $eventDate = Carbon::createFromFormat('Y-m-d H:i:s',
                $position->eventdate)->locale('pt_BR')
              ;

              if ($this->limitTextToSingleLine) {
                // Limita o endereço para que o mesmo seja exibido em
                // uma única linha
                $address = $this->limitWidth($position->address, 480,
                  $this->textSize, $this->font
                );
              } else {
                $address = $position->address;
              }

              // Cada registro é analisado e adicionado ou descartado
              // levando-se em consideração as sequências em que os
              // mesmos foram executados, de forma a permitir obtermos
              // as informações de viagens executadas por cada
              // motorista
              $roadtrip->parse($position->id, $position->driverid,
                $position->plate, $eventDate, $position->rs232,
                $address
              );
            }

            // Ao final, garantimos que qualquer viagem ainda não
            // encerrada seja computada
            $roadtrip->close();

            // Agora recuperamos os eventos das viagens executadas
            $events = $roadtrip->getRoadTrips();
            $this->debug("Recuperado(s) {amount} eventos de viagem.",
              [ 'amount' => count($events) ]
            );
            
            $error = null;
          } else {
            $error = 'Nenhum registro retornado.';
          }
        }
        catch(InvalidArgumentException $exception) {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno no banco de dados: {error}.",
            [ 'module' => 'viagens executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "viagens executadas. Erro interno no banco de dados."
          ;
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno no banco de dados: {error}.",
            [ 'module' => 'viagens executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "viagens executadas. Erro interno no banco de dados."
          ;
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível recuperar as informações de "
            . "{module}. Erro interno: {error}.",
            [ 'module' => 'viagens executadas',
              'error'  => $exception->getMessage() ]
          );

          $error = "Não foi possível recuperar as informações de "
            . "viagens executadas. Erro interno."
          ;
        }
      } else {
        $error = "Selecione o cliente, funcionário e período desejado";
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Viagens executadas";
    $PDFFileName = "RoadTrips_{$clientID}_{$driverID}_from_{$startDate}_to_{$endDate}.pdf";
    $page = $this->renderPDF('stc/report/roadtrips/PDFroadtrips.twig',
      [ 'startDate'  => $args['startDate'],
        'endDate'    => $args['endDate'],
        'driver'     => $driver,
        'events'     => $events,
        'error'      => $error ])
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
    $mpdf->SetSubject('Viagens executadas');
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
