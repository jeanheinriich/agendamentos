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
 * O controlador do gerenciamento de dados de posicionamento obtidos no
 * sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Data;

use App\Models\STC\Customer;
use App\Models\STC\Position;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\Services\PositionService;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Geocode\Providers\OpenStreetMap;
use Core\HTTP\HTTPService;
use Core\HTTP\Progress\ServerSentEvent;
use Core\Streams\ServerSentEventHandler;
use Exception;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class PositionsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de dados de posicionamento.
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
    $this->breadcrumb->push('Dados', '');
    $this->breadcrumb->push('Posicionamento',
      $this->path('STC\Data\Positions')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de dados de posicionamento.");

    // Recupera os dados da sessão
    $position = $this->session->get('position',
      [ 'searchValue' => '',
        'period' => 1 ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/data/positions/positions.twig',
      [ 'position' => $position ])
    ;
  }
  
  /**
   * Recupera a relação dos clientes em formato JSON.
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
    $this->debug("Acesso ao histórico de posicionamento.");
    
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
    $order = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);
    
    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];
    
    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem
    
    // O campo de pesquisa selecionado
    $searchValue = $postParams['searchValue'];
    $period = intval($postParams['period']);
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('position',
      [ 'searchValue' => $searchValue,
        'period' => $period ]
    );
    
    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $PositionQry = Position::where('positions.contractorid', '=',
        $contractor->id
      );

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
        
        // Limita os dados apenas à veículos deste cliente
        $PositionQry
          ->join('stc.vehicles',
            function($join) {
              $join->on('positions.plate', '=', 'vehicles.plate');
              $join->on('positions.contractorid', '=',
                'vehicles.contractorid'
              );
            })
          ->where('vehicles.clientid', '=', $customer->id)
        ;

        // Registra que estamos limitando
        $this->debug("Limitando os dados de posicionamentos aos "
          . "veículos do cliente {name}.",
          [ 'name'  => $customer->name ]
        );
      }
      
      // Acrescenta os filtros
      if (!empty($searchValue)) {
        $PositionQry->whereRaw("public.unaccented(plate) ILIKE "
          . "public.unaccented(E'%{$searchValue}%')"
        );
      }

      // Adiciona o período, se necessário
      if ($period > 0) {
        // O período está em dias
        $PositionQry
          ->whereRaw("positions.eventdate > "
              . "current_date - interval '{$period}' day"
            )
        ;
      }

      // Conclui nossa consulta
      $positions = $PositionQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'positions.registreid AS id',
            'positions.positionid',
            'positions.plate',
            'positions.eventdate',
            'positions.ignitionstatus',
            'positions.address',
            'positions.latitude',
            'positions.longitude',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($positions) > 0) {
        $rowCount = $positions[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $positions
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos histórico de posicionamentos "
            . "cadastrados."
          ;
        } else {
          $error = "Não temos histórico de posicionamentos cadastrados "
            . "para o veículo placa <i>{$searchValue}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'histórico de posicionamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de posicionamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'histórico de posicionamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de posicionamentos. Erro interno."
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
   * Sincroniza o histórico de posicionamentos com o site do STC,
   * fazendo as devidas modificações na base de dados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function synchronize(Request $request, Response $response)
  {
    // Registra o acesso
    $this->info("Processando o sincronismo do histórico de "
      . "posicionamento com o site do STC."
    );

    // Recuperamos as configurações de integração ao sistema STC
    $settings = $this->container['settings']['integration']['stc'];
    $url      = $settings['url'];
    $method   = $settings['method'];
    $path     = $settings['path'];

    // Criamos o mecanismo para envio de eventos para o cliente
    $serverEvent = new ServerSentEvent();

    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    if (empty($contractor->stckey)) {
      // Aborta o processamento e exibe a mensagem de erro
      header('Content-Type: text/event-stream');
      header('Cache-Control: no-cache');
      
      $serverEvent->send('ERROR', 0, 0, "ERRO: O contratante "
        . "{$contractor->name} não possui uma chave de cliente do "
        . "sistema STC. O processamento não pode ser realizado."
      );

      return;
    }

    // Criamos um serviço para acesso à API deste provedor através do
    // protocolo HTTP
    $httpService = new HTTPService($url, $method, $path);

    // Criamos nosso sincronizador de dados com este provedor
    $synchronizer = new STCDataSynchronizer($httpService,
      $this->logger, $serverEvent)
    ;
    $synchronizer->disableProgressDuringProcessing();

    // Inicializamos à API para acesso ao OpenStreetMap
    $cacheDir = $synchronizer->getCookiePath();
    $openStreetMap = new OpenStreetMap($cacheDir);

    // Recuperamos o acesso ao banco de dados
    $DB = $this->container->get('DB');

    // Inicializamos o serviço de obtenção do histórico de
    // posicionamentos
    $positionService = new PositionService($synchronizer,
      $this->logger, $contractor, $DB)
    ;
    $positionService->setGeocoderProvider($openStreetMap);

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $positionService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // histórico de posicionamentos
        $positionService->synchronize();
      }
      catch (Throwable $error)
      {
        $this->error($error->getMessage());
        $serverEvent->send('ERROR', 0, 0, $error->getMessage());
      }

      return '';
    });
    
    return $response
      ->withHeader('Content-Type', 'text/event-stream')
      ->withHeader('Cache-Control', 'no-cache')
        // Desativa o buffer FastCGI no Nginx
      ->withHeader('X-Accel-Buffering', 'no')
      ->withBody($output)
    ;
  }
}
