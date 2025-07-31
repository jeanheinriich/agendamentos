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
 * O controlador do gerenciamento de equipamentos de rastreamento
 * cadastrados no sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Cadastre;

use App\Models\STC\Customer;
use App\Models\STC\Device;
use App\Models\STC\Driver;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\STCJob;
use App\Providers\STC\Services\DeviceService;
use App\Providers\STC\Tasks\DeleteDriversStoredInEquipment;
use App\Providers\STC\Tasks\DeleteAllDriversStoredInEquipment;
use App\Providers\STC\Tasks\InsertDriversNotRegisteredInEquipment;
use App\Providers\STC\Tasks\RequestDriversInEquipment;
use App\Providers\STC\Tasks\ReadDriversStoredInEquipment;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\HTTP\HTTPService;
use Core\HTTP\Progress\ServerSentEvent;
use Core\Streams\ServerSentEventHandler;
use Exception;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class EquipmentsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * A URI para o serviço que nos permite requisitar à STC que atualize
   * as informações de ID's de motoristas cadastrados no equipamento e
   * as armazene para consulta. Este serviço não retorna nada e dispende
   * um tempo para ser executado, já que envia ao rastreador o pedido e
   * aguarda a transmissão dos dados nele armazenados.
   *
   * @var string
   */
  protected $requestDriversInEquipmentURI = 'ws/device/sgbras/getalldriverid';

  /**
   * A URI para o serviço que nos permite ler as informações de ID's de
   * motoristas cadastrados no equipamento e que foram requisitadas pelo
   * serviço 'requestDriversInEquipmentURI'. Em média o recebimento dos
   * dados leva em torno de 4 minutos (acrescentamos mais 2 minutos por
   * tolerância). Então este serviço somente é executado após decorrido
   * os 6 minutos de intervalo.
   *
   * @var string
   */
  protected $getDriversInEquipmentURI = 'ws/device/sgbras/listdriveridenabled';

  /**
   * A URI para o serviço que nos permite adicionar um ou mais ID's de
   * motoristas no equipamento.
   *
   * @var string
   */
  protected $addDriversURI = 'ws/device/sgbras/adddriverid';

  /**
   * A URI para o serviço que nos permite remover um ou mais ID's de
   * motoristas do equipamento.
   *
   * @var string
   */
  protected $delDriversURI = 'ws/device/sgbras/deletedriverid';

  /**
   * A URI para o serviço que nos permite verificar se o comando enviado
   * foi corretamente concluído.
   *
   * @var string
   */
  protected $checkCommandURI = 'ws/device/sgbras/getcommandpendent';

  /**
   * Exibe a página inicial do gerenciamento de equipamentos de
   * rastreamento.
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
    $this->breadcrumb->push('Equipamentos',
      $this->path('STC\Cadastre\Equipments')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de equipamentos de "
      . "rastreamento."
    );

    // Recupera os dados da sessão
    $equipment = $this->session->get('equipment',
      [ 'searchValue' => '' ]);
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/equipments/equipments.twig',
      [ 'equipment' => $equipment ])
    ;
  }
  
  /**
   * Recupera a relação dos equipamentos de rastreamento em formato JSON
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
    $this->debug("Acesso à relação de equipamentos de rastreamento.");
    
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
    $searchField = $postParams['searchField'];
    $searchValue = $postParams['searchValue'];

    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid < 6) {
      $clientID     = $postParams['clientID'];
      $customerName = $postParams['customerName'];
    } else {
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
      $clientID     = $customer->id;
      $customerName = $customer->name;
    }
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('equipment',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'customer' => [
          'id'   => $clientID,
          'name' => $customerName
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
      $EquipmentQry = Device::join('stc.manufactures',
            'devices.manufactureid','=','manufactures.manufactureid'
          )
        ->leftJoin('stc.vehicles', function($join) {
            $join->on('devices.plate', '=', 'vehicles.plate');
            $join->on('devices.contractorid', '=', 'vehicles.contractorid');
          })
        ->leftJoin('stc.devicemodels', function($join) {
            $join->on('devices.devicemodelid', '=', 'devicemodels.devicemodelid');
            $join->on('devices.contractorid', '=', 'devicemodels.contractorid');
          })
        ->where('devices.contractorid', '=', $contractor->id)
      ;
      
      // Acrescenta os filtros
      if (!empty($searchValue)) {
        switch ($searchField) {
          case 'deviceID':
            // Filtra por parte do nome
            $EquipmentQry
              ->whereRaw("devices.deviceid::TEXT ILIKE "
                  . "'%{$searchValue}%'"
                )
            ;

            break;
          default:
            // Filtra pelo campo indicado
            $EquipmentQry
              ->whereRaw("public.unaccented(devices.plate) ILIKE "
                  . "public.unaccented(E'%{$searchValue}%')"
                )
            ;
        }
        $this->debug("Pesquisando por '{$searchField}' que contenham "
          . "'{$searchValue}'"
        );
      }
      if (!empty($clientID)) {
        // Informado o cliente
        $EquipmentQry->where("vehicles.clientid", $clientID);
        $this->debug("Filtrando pelo cliente '[{$clientID}] "
          . "{$customerName}'"
        );
      }

      // Conclui nossa consulta
      $equipments = $EquipmentQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'devices.deviceid AS id',
            'devices.manufactureid',
            'manufactures.name AS manufacturename',
            'devices.plate',
            'devices.ownername',
            'devices.ownertype',
            'devices.lastcommunication',
            'devicemodels.name AS devicemodelname',
            $this->DB->raw(''
              . 'CASE'
              . '  WHEN devicemodels.abletokeyboard IS NULL THEN false '
              . '  ELSE devicemodels.abletokeyboard '
              . 'END AS abletokeyboard'
            ),
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($equipments) > 0) {
        $rowCount = $equipments[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $equipments
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($searchValue), empty($clientID))) {
          case 1:
            // Informado apenas o campo de pesquisa
            switch ($searchField) {
              case 'deviceID':
                $error = "Não temos dispositivos cadastrados cujo ID "
                  . "do dispositivo contém <i>{$searchValue}</i>."
                ;
                break;
              default:
                $error = "Não temos dispositivos cadastrados cuja "
                  . "placa do veículo contenha <i>{$searchValue}</i>."
                ;
            }

            break;
          case 2:
            // Informado apenas o cliente
            $error = "Não temos dispositivos cadastrados no cliente "
              . "<i>{$customerName}</i>."
            ;

            break;
          case 3:
            // Informado tanto o campo de pesquisa quanto o ID do
            // cliente
            $error = "Não temos dispositivos cadastrados no cliente "
              . "<i>{$customerName}</i> e ";
            switch ($searchField) {
              case 'deviceID':
                $error .= "cuja ID do dispositivo contenha "
                  . "<i>{$searchValue}</i>."
                ;
                break;
              default:
                $error .= "a placa do veículo contenha "
                  . "<i>{$searchValue}</i>."
                ;
            }

            break;
          default:
            $error = "Não temos equipamentos de rastreamento "
              . "cadastrados."
            ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'equipamentos de rastreamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos de rastreamento. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'equipamentos de rastreamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos de rastreamento. Erro interno."
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
   * Sincroniza a relação dos equipamentos de rastreamento com o site
   * do STC, fazendo as devidas modificações na base de dados.
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
    $this->info("Processando o sincronismo da relação de "
      . "equipamentos de rastreamento com o site do STC."
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

    // Inicializamos o serviço de obtenção dos equipamentos de
    // rastreamento
    $deviceService = new DeviceService($synchronizer, $this->logger,
      $contractor, $DB);

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $deviceService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // equipamentos de rastreamento
        $deviceService->synchronize();
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
   * Exibe a página inicial do sincronismo de informações de motoristas
   * em um equipamento de rastreamento.
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
  public function driversshow(Request $request, Response $response,
    array $args)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera a ID do equipamento para o qual faremos o sincronismo
    $equipmentID = $args['equipmentID'];

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('STC\Cadastre\Equipments')
    );
    $this->breadcrumb->push('Motoristas',
      $this->path('STC\Cadastre\Equipments\Drivers', [
        'equipmentID' => $equipmentID
      ])
    );

    // Recupera as informações do equipamento
    $equipment = Device::join('stc.manufactures',
          'devices.manufactureid','=','manufactures.manufactureid'
        )
      ->leftJoin('stc.devicemodels',
        function($join) {
          $join->on('devices.devicemodelid', '=', 'devicemodels.devicemodelid');
          $join->on('devices.contractorid', '=',
            'devicemodels.contractorid'
          );
        })
      ->join('stc.vehicles',
        function($join) {
          $join->on('devices.plate', '=', 'vehicles.plate');
          $join->on('devices.contractorid', '=',
            'vehicles.contractorid'
          );
        })
      ->join('stc.vehicletypes',
        function($join) {
          $join->on('vehicles.vehicletypeid', '=',
            'vehicletypes.vehicletypeid'
          );
          $join->on('vehicles.contractorid', '=',
            'vehicletypes.contractorid'
          );
        })
      ->join('stc.vehiclemodels',
        function($join) {
          $join->on('vehicles.vehiclemodelid', '=',
            'vehiclemodels.vehiclemodelid'
            );
          $join->on('vehicles.contractorid', '=',
            'vehiclemodels.contractorid'
          );
        })
      ->join('stc.vehiclebrands',
        function($join) {
          $join->on('vehiclemodels.vehiclebrandid', '=',
            'vehiclebrands.vehiclebrandid'
          );
          $join->on('vehiclemodels.contractorid', '=',
            'vehiclebrands.contractorid'
          );
        })
      ->where('devices.contractorid', '=', $contractor->id)
      ->where('devices.deviceid', '=', $equipmentID)
      ->get([
          'devices.deviceid AS deviceid',
          'manufactures.name AS manufacturename',
          'devicemodels.name AS devicemodelname',
          'devices.plate',
          'devices.ownername',
          'devices.ownertype',
          'devices.lastcommunication',
          'vehicletypes.name AS vehicletypename',
          'vehiclebrands.name AS vehiclebrandname',
          'vehiclemodels.name AS vehiclemodelname'
        ])
      ->first()
    ;
    
    // Registra o acesso
    $this->info("Acesso ao sincronismo de motoristas cadastrados no "
      . "equipamento {equipmentID} instalado no veículo placa {plate}.",
      [ "equipmentID" => $equipmentID,
        "plate" => $equipment->plate
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/equipments/syncdrivers.twig',
      [ 'equipmentID' => $equipmentID,
        'equipment' => $equipment ])
    ;
  }

  /**
   * Sincroniza as informações de motoristas com os teclados instalados
   * nos veículos através do site da STC.
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
  public function driverssynchronize(Request $request,
    Response $response, array $args)
  {
    // Recupera a ID do equipamento para o qual faremos o sincronismo
    $equipmentID = $args['equipmentID'];

    // Registra o acesso
    $this->info("Processando o sincronismo da relação de motoristas "
      . "com teclado acoplado ao equipamento '{equipmentID}'.", [
      'equipmentID' => $equipmentID ]
    );

    // Recuperamos as configurações de integração ao sistema STC
    $settings = $this->container['settings']['integration']['stc'];
    $url      = $settings['url'];
    $method   = $settings['method'];
    $path     = $settings['path'];

    // Criamos uma exibição de progresso em modo callback
    $serverEvent = new ServerSentEvent();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    if (empty($contractor->stckey)) {
      // Aborta o processamento e exibe a mensagem de erro
      header('Content-Type: text/event-stream');
      header('Cache-Control: no-cache');

      $serverEvent->send('ERROR', 0, 3, "ERRO: O contratante "
        . "{$contractor->name} não possui uma chave de cliente do "
        . "sistema STC. O processamento não pode ser realizado."
      );

      return;
    }

    // Criamos um serviço para acesso à API deste provedor através do
    // protocolo HTTP
    $httpService = new HTTPService($url, $method, $path);

    // Criamos um trabalho para lidar com as requisições à STC
    $deviceJob = new STCJob($httpService, $this->logger, $serverEvent);

    // Adiciona cada uma das tarefas
    // 1. Remoção dos dados de todos os motoristas
    //$deleteAllDrivers = new DeleteAllDriversStoredInEquipment($this->logger);
    //$deviceJob->addTask($deleteAllDrivers);
    // 1. Requisição de dados de motoristas
    $requestDrivers = new RequestDriversInEquipment($this->logger);
    $deviceJob->addTask($requestDrivers);
    // 2. Leitura de dados de motoristas
    $readDrivers = new ReadDriversStoredInEquipment($this->logger);
    $drivers = $this->getDriversOnDevice($equipmentID);
    $readDrivers->setDrivers($drivers);
    $deviceJob->addTask($readDrivers);
    // 3. Remoção de ID's de motoristas armazenados no dispositivo e que
    //    não estejam no cadastro de motoristas e/ou cadastros em
    //    duplicidade no equipamento
    $deleteDriversStoredInEquipment =
      new DeleteDriversStoredInEquipment($this->logger)
    ;
    $deviceJob->addTask($deleteDriversStoredInEquipment);
    // 4. Inserção de ID's de motoristas que estão no cadastro de
    //    motoristas, mas que não estejam cadastrados no equipamento
    $insertNotRegisteredDrivers =
      new InsertDriversNotRegisteredInEquipment($this->logger)
    ;
    $deviceJob->addTask($insertNotRegisteredDrivers);

    // Passamos a chave de conexão
    $deviceJob->setKey($contractor->stckey);

    // Passamos o ID do dispositivo
    $deviceJob->setDevice($equipmentID);

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $deviceJob)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // motorista neste dispositivo
        $deviceJob->prepareParameters();
        $deviceJob->execute();
      }
      catch (Throwable $error)
      {
        $this->error($error->getMessage());
        $serverEvent->send('ERROR', 0, 3, $error->getMessage());
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
   * Recupera as informações de IDs de motoristas para o cliente no qual
   * o dispositivo está vinculado.
   *
   * @param int $deviceID
   *   A ID do dispositivo
   *
   * @return array
   *   A matriz com as informações de motoristas
   */
  protected function getDriversOnDevice(int $deviceID): array
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      $customer = Device::leftJoin('stc.vehicles', function($join) {
            $join->on('devices.plate', '=', 'vehicles.plate');
            $join->on('devices.contractorid', '=', 'vehicles.contractorid');
          })
        ->where('devices.contractorid', '=', $contractor->id)
        ->where('devices.deviceid', '=', $deviceID)
        ->get([
            'vehicles.clientid AS id'
          ])
        ->first()
      ;

      $clientID = $customer->id;

      $this->debug("Solicitando as informações de motoristas para o "
        . "cliente {clientID}",
        [ 'clientID'  => $clientID ]
      );

      $drivers = Driver::where("drivers.contractorid",
            '=', $contractor->id
          )
        ->where("drivers.clientid", '=', $clientID)
        ->orderBy('drivers.driverid')
        ->get([
            'drivers.driverid'
          ])
        ->toArray()
      ;

      $this->debug("Solicitada as informações de motoristas para o "
        . "cliente {clientID} e retornado {count}",
        [ 'clientID'  => $clientID,
          'count' => count($drivers) ]
      );
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "motoristas. Erro interno no banco de dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $drivers = null;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "motoristas. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $drivers = null;
    }

    if ($drivers) {
      return array_column($drivers, 'driverid');
    }

    return [];
  }
}
