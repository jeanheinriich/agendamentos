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
 * O controlador do gerenciamento de veículos cadastrados no sistema
 * STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Cadastre;

use App\Models\STC\Customer;
use App\Models\STC\Vehicle;
use App\Models\STC\Device;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\Services\VehicleService;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\HTTP\HTTPService;
use Core\HTTP\Progress\ServerSentEvent;
use Core\Streams\ServerSentEventHandler;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class VehiclesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;
  
  /**
   * Exibe a página inicial do gerenciamento de veículos.
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
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos',
      $this->path('STC\Cadastre\Vehicles')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de veículos.");

    // Recupera os dados da sessão
    $vehicle = $this->session->get('vehicle',
      [ 'searchValue' => '',
        'customer' => [
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
      $this->info("Recuperando dados do cliente");
      $customer = Customer::where('contractorid', '=', $contractor->id)
        ->where('customerid', '=', $customerID)
        ->get([
            'clientid AS id',
            'name'
          ])
        ->first()
      ;
      $this->info("Recuperado dados do cliente");
      
      // Força a seleção dos dados deste cliente
      $vehicle['customer'] = [
        'id'   => $customer->id,
        'name' => $customer->name
      ];
    }
    
    // Renderiza a página
    $this->info("Iniciando renderização");
    $page = $this->render($request, $response,
      'stc/cadastre/vehicles/vehicles.twig',
      [ 'vehicle' => $vehicle ])
    ;
    $this->info("Finalizada renderização");

    return $page;
  }

  /**
   * Recupera a relação dos veículos em formato JSON.
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
    $this->debug("Acesso à relação de veículos.");

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
    } else {
      // Obtemos os dados do cliente dos parâmetros da requisição
      $clientID = $postParams['clientID'];
      $customerName = $postParams['customerName'];
    }

    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicle',
      [ 'searchValue' => $searchValue,
        'customer' => [
          'id' => $clientID,
          'name'  => $customerName
        ]
      ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleQry = Vehicle::join('stc.vehicletypes',
          function($join) {
            $join->on('vehicles.vehicletypeid', '=',
              'vehicletypes.vehicletypeid'
            );
            $join->on('vehicles.contractorid', '=',
              'vehicletypes.contractorid'
            );
          })
        ->leftJoin('stc.vehiclemodels',
          function($join) {
            $join->on('vehicles.vehiclemodelid', '=',
              'vehiclemodels.vehiclemodelid'
            );
            $join->on('vehicles.contractorid', '=',
              'vehiclemodels.contractorid'
            );
          })
        ->leftJoin('stc.vehiclebrands',
          function($join) {
            $join->on('vehiclemodels.vehiclebrandid', '=',
              'vehiclebrands.vehiclebrandid'
            );
            $join->on('vehiclemodels.contractorid', '=',
              'vehiclebrands.contractorid'
            );
          })
        ->leftJoin('stc.customers',
          function($join) {
            $join->on('vehicles.clientid', '=',
              'customers.clientid'
            );
            $join->on('vehicles.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('vehicles.contractorid', '=', $contractor->id)
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($searchValue), empty($clientID))) {
        case 1:
          // Informado apenas a placa do veículo
          $VehicleQry
            ->whereRaw("public.unaccented(vehicles.plate) "
                . "ILIKE public.unaccented(E'%{$searchValue}%')"
              )
          ;
          $this->debug("Informado apenas a placa '{$searchValue}'");

          break;
        case 2:
          // Informado apenas o cliente
          $VehicleQry->where('vehicles.clientid', '=', $clientID);
          $this->debug("Informado apenas o cliente '{$clientID}'");

          break;
        case 3:
          // Informado tanto a placa do veículo quanto o cliente
          $VehicleQry
            ->whereRaw("public.unaccented(vehicles.plate) "
                . "ILIKE public.unaccented(E'%{$searchValue}%')"
              )
            ->where('vehicles.clientid', '=', $clientID)
          ;
          $this->debug("Informado tanto a placa '{$searchValue}' "
            . "quanto o cliente '{$clientID}'"
          );
      }

      // Conclui nossa consulta
      $vehicles = $VehicleQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehicles.id',
            'vehicles.plate',
            'vehicletypes.name AS vehicletypename',
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'customers.name AS customername',
            'vehicles.status',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicles) > 0) {
        $rowCount = $vehicles[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicles
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($searchValue), empty($clientID))) {
          case 1:
            // Informado apenas a placa do veículo
            $error = "Não temos veículos cadastrados cuja placa seja "
              . "'{$searchValue}'"
            ;

            break;
          case 2:
            // Informado apenas o cliente
            $this->debug("Não temos veículos cadastrados que pertençam "
              . "ao cliente '{$clientID}'"
            );

            break;
          case 3:
            // Informado tanto a placa do veículo quanto o cliente
            $error = "Não temos veículos cadastrados cuja placa seja "
              . "'{$searchValue}' e que pertençam ao cliente "
              . "'{$clientID}'"
            ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de veículos. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de veículos. "
        . "Erro interno."
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
   * Exibe um formulário para visualização das informações cadastrais de
   * um veículo, sem permitir a sua edição.
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
  public function view(Request $request, Response $response,
    array $args)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    try
    {
      // Recupera o código do veículo enviado na requisição
      $vehicleID = $args['vehicleID'];

      // Verifica se precisa limitar o que estamos exibindo em função
      // das permissões deste usuário
      if ($this->authorization->getUser()->groupid > 5) {
        // Recuperamos o código do cliente
        $customerID = $this->authorization->getUser()->entityid;

        // Recuperamos os dados do cliente através do código do cliente
        $customer = Customer::where('contractorid', '=', $contractor->id)
          ->where('customerid', '=', $customerID)
          ->get([
              'clientid AS id'
            ])
          ->first()
        ;
        
        // Força a seleção dos dados deste cliente
        $clientID = $customer->id;
      } else {
        // Recupera o código do cliente enviado na requisição
        $clientID = $args['clientID'];
      }

      // Recupera as informações do veículo
      $VehicleQry = Vehicle::join('stc.vehicletypes',
          function($join) {
            $join->on('vehicles.vehicletypeid', '=',
              'vehicletypes.vehicletypeid'
            );
            $join->on('vehicles.contractorid', '=',
              'vehicletypes.contractorid'
            );
          })
        ->leftJoin('stc.vehiclemodels',
          function($join) {
            $join->on('vehicles.vehiclemodelid', '=',
              'vehiclemodels.vehiclemodelid'
            );
            $join->on('vehicles.contractorid', '=',
              'vehiclemodels.contractorid'
            );
          })
        ->leftJoin('stc.vehiclebrands',
          function($join) {
            $join->on('vehiclemodels.vehiclebrandid', '=',
              'vehiclebrands.vehiclebrandid'
            );
            $join->on('vehiclemodels.contractorid', '=',
              'vehiclebrands.contractorid'
            );
          })
        ->leftJoin('stc.customers',
          function($join) {
            $join->on('vehicles.clientid', '=', 'customers.clientid');
            $join->on('vehicles.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('vehicles.contractorid', '=', $contractor->id)
        ->where('vehicles.id', '=', $vehicleID)
      ;

      // Acrescenta os filtros
      if ($clientID > 0) {
        $VehicleQry->where('vehicles.clientid', '=', $clientID);
      }

      // Conclui nossa consulta
      $vehicle = $VehicleQry
        ->get([
            'vehicles.*',
            'vehicletypes.name AS vehicletypename',
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'customers.name AS customername'
          ])
        ->toArray()[0]
      ;

      // Registra o acesso
      $this->debug("Processando à visualização do veículo placa "
        . "'{plate}'.",
        [ 'plate' => $vehicle['plate'] ]
      );

      // Carrega os dados atuais
      $this->validator->setValues($vehicle);
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o veículo código "
        . "{vehicleID}.",
        [ 'vehicleID' => $vehicleID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este veículo.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Cadastre\Vehicles' ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'STC\Cadastre\Vehicles');
    }

    // Exibe um formulário para visualização dos dados cadastrais de um
    // veículo

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos',
      $this->path('STC\Cadastre\Vehicles')
    );
    $this->breadcrumb->push('Visualizar',
      $this->path('STC\Cadastre\Vehicles\View', [
        'vehicleID' => $vehicleID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à visualização dos dados cadastrais do veículo "
      . "'{plate}'.",
      [ 'plate' => $vehicle['plate'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/vehicles/vehicle.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }

  /**
   * Sincroniza a relação dos veículos com o site do STC, fazendo as
   * devidas modificações na base de dados.
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
    $this->info("Processando o sincronismo da relação de veículos "
      . "com o site do STC."
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

    // Recuperamos o acesso ao banco de dados
    $DB = $this->container->get('DB');

    // Inicializamos o serviço de obtenção dos veículos
    $vehicleService = new VehicleService($synchronizer, $this->logger,
      $contractor, $DB)
    ;

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $vehicleService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // veículos
        $vehicleService->synchronize();
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

  /**
   * Recupera a relação dos veículos em formato JSON no padrão dos
   * campos de preenchimento automático.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(Request $request,
    Response $response)
  {
    $this->debug("Relação de veículos para preenchimento automático "
      . "despachada."
    );


    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams       = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor       = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $plate            = addslashes($postParams['searchTerm']);
    $clientID         = $postParams['clientID'];
    $onlyWithKeyboard = false;
    if (array_key_exists('onlyWithKeyboard', $postParams)) {
      $onlyWithKeyboard = true;
    }

    // Determina os limites e parâmetros da consulta
    $start         = 0;
    $length        = $postParams['limit'];
    $ORDER         = 'vehicles.plate ASC';
    
    if ($onlyWithKeyboard) {
      $this->debug("Acesso aos dados de preenchimento automático dos "
        . "veículos cuja placa contém '{plate}', possuam teclado e "
        . "sejam do cliente '{clientid}",
        [ 'plate' => $plate,
          'clientid' => $clientID ]
      );
    } else {
      $this->debug("Acesso aos dados de preenchimento automático dos "
        . "veículos cuja placa contém '{plate}' e sejam do cliente "
        . "'{clientid}",
        [ 'plate' => $plate,
          'clientid' => $clientID ]
      );
    }
    
    try
    {
      // Localiza os veículos na base de dados
      $message = "Veículos cuja placa contém '{$plate}'";
      if ($onlyWithKeyboard) {
        $vehicles = Vehicle::join('stc.customers',
            function($join) {
              $join->on('vehicles.clientid', '=', 'customers.clientid');
              $join->on('vehicles.contractorid', '=',
                'customers.contractorid'
              );
            })
          ->join('stc.devices',
            function($join) {
              $join->on('vehicles.deviceid', '=', 'devices.deviceid');
              $join->on('vehicles.contractorid', '=',
                'devices.contractorid'
              );
            })
          ->join('stc.devicemodels',
            function($join) {
              $join->on('devices.devicemodelid', '=',
                'devicemodels.devicemodelid'
              );
              $join->on('devices.contractorid', '=',
                'devicemodels.contractorid'
              );
            })
          ->whereRaw("public.unaccented(vehicles.plate) ILIKE "
              . "public.unaccented('%{$plate}%')"
            )
          ->where("vehicles.contractorid", '=', $contractor->id)
          ->where("vehicles.clientid", '=', $clientID)
          ->where("devicemodels.abletokeyboard", true)
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'vehicles.id',
              'vehicles.clientid',
              'vehicles.plate',
              'vehicles.deviceid',
              'customers.name AS customername'
            ])
        ;
      } else {
        $vehicles = Vehicle::join('stc.customers',
            function($join) {
              $join->on('vehicles.clientid', '=', 'customers.clientid');
              $join->on('vehicles.contractorid', '=',
                'customers.contractorid'
              );
            })
          ->whereRaw("public.unaccented(vehicles.plate) ILIKE "
              . "public.unaccented('%{$plate}%')"
            )
          ->where("vehicles.contractorid", '=', $contractor->id)
          ->where("vehicles.clientid", '=', $clientID)
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'vehicles.id',
              'vehicles.clientid',
              'vehicles.plate',
              'vehicles.deviceid',
              'customers.name AS customername'
            ])
        ;
      }
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $vehicles
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'motoristas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "motoristas para preenchimento automático. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'motoristas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "motoristas para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar motoristas cujo "
            . "nome contém '$plate'",
          'data' => null
        ])
    ;
  }
}
