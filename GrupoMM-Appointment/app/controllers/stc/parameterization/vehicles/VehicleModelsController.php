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
 * O controlador do gerenciamento de modelos de veículos cadastrados no
 * sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Parameterization\Vehicles;

use App\Models\STC\VehicleBrand;
use App\Models\STC\VehicleModel;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\Services\VehicleModelService;
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

class VehicleModelsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de modelos de veículos.
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
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('STC\Parameterization\Vehicles\Models')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de modelos de veículos.");
    
    // Recupera os dados da sessão
    $vehicle = $this->session->get('vehicle',
      [ 'brand' => [
          'id' => 0,
          'name' => ''
        ],
        'model' => [
          'name' => ''
        ]
      ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/parameterization/vehicles/models/vehiclemodels.twig',
      [ 'vehicle' => $vehicle ])
    ;
  }

  /**
   * Recupera a relação dos modelos de veículos em formato JSON.
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
    $this->debug("Acesso à relação de modelos de veículos.");
    
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
    $name      = $postParams['searchValue'];
    $brandID   = $postParams['brandID'];
    $brandName = $postParams['brandName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicle',
      [ 'brand' => [
          'id' => $brandID,
          'name' => $brandName
        ],
        'model' => [
          'name' => $name
        ]
      ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleModelQry = VehicleModel::join('stc.vehiclebrands',
          function($join) {
            $join->on('vehiclemodels.vehiclebrandid', '=',
              'vehiclebrands.vehiclebrandid'
            );
            $join->on('vehiclemodels.contractorid', '=',
              'vehiclebrands.contractorid'
            );
          })
        ->where('vehiclemodels.contractorid', '=', $contractor->id)
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($brandID))) {
        case 1:
          // Informado apenas o nome do modelo de veículo
          $VehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas a marca do veículo
          $VehicleModelQry
            ->where('vehiclemodels.vehiclebrandid', '=', $brandID)
          ;

          break;
        case 3:
          // Informado tanto o nome do modelo de veículo quanto a marca
          // do veículo
          $VehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
            ->where('vehiclemodels.vehiclebrandid', '=', $brandID)
          ;
          
          break;
        default:
          // Não adiciona nenhum filtro
      }

      // Conclui nossa consulta
      $vehicleModels = $VehicleModelQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclemodels.vehiclemodelid AS id',
            'vehiclemodels.name',
            'vehiclebrands.name AS vehiclebrandname',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicleModels) > 0) {
        $rowCount = $vehicleModels[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicleModels
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($brandID))) {
          case 1:
            // Informado apenas o nome do modelo de veículo
            $error = "Não temos modelos de veículos cadastrados cujo "
              . "nome contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas a marca do veículo
            $error = "Não temos modelos de veículos cadastrados da "
              . "marca <i>{$brandName}</i>."
            ;

            break;
          case 3:
            // Informado tanto o nome do modelo de veículo quanto a
            // marca do veículo
            $error = "Não temos modelos de veículos cadastrados da "
              . "marca <i>{$brandName}</i> cujo nome contém "
              . "<i>{$name}</i>."
            ;
            
            break;
          default:
            $error = "Não temos modelos de veículos cadastrados.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'modelos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de veículos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'modelos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de veículos. Erro interno."
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
   * Sincroniza a relação dos modelos de veículos com o site do STC,
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
    $this->info("Processando o sincronismo da relação de modelos "
      . "de veículos com o site do STC."
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

    // Inicializamos o serviço de obtenção dos modelos de veículos
    $modelService = new VehicleModelService($synchronizer,
      $this->logger, $contractor, $DB)
    ;

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $modelService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // modelos de veículos
        $modelService->synchronize();
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
