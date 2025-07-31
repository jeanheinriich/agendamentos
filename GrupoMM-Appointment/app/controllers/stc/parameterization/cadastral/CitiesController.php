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
 * O controlador do gerenciamento de cidades cadastradas no sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Parameterization\Cadastral;

use App\Models\STC\City;
use App\Models\State;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\Services\CityService;
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

class CitiesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de cidades.
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
    $this->breadcrumb->push('Cadastral', '');
    $this->breadcrumb->push('Cidades',
      $this->path('STC\Parameterization\Cadastral\Cities')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de cidades.");

    // Recupera as informações de estados (UFs)
    $states = State::orderBy('state')
      ->get([
          'state AS id',
          'name'
        ])
    ;
    
    // Recupera os dados da sessão
    $city = $this->session->get('city',
      [ 'name' => '',
        'state' => [
          'id' => 'ALL',
          'name' => 'Todos'
        ]
      ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/parameterization/cadastral/cities/cities.twig',
      [ 'city' => $city,
        'states' => $states ])
    ;
  }

  /**
   * Recupera a relação das cidades em formato JSON.
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
    $this->debug("Acesso à relação de cidades.");
    
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
    $name = $postParams['searchValue'];
    $stateID   = $postParams['stateID'];
    $stateName = $postParams['stateName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('city',
      [ 'name' => $name,
        'state' => [
          'id' => $stateID,
          'name' => $stateName
        ]
      ]
    );

    if ($stateID === 'ALL') {
      $stateID = '';
    }
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $CityQry = City::where('contractorid', '=', $contractor->id);
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($stateID))) {
        case 1:
          // Informado apenas o nome
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas a UF
          $CityQry->where("state", $stateID);

          break;
        case 3:
          // Informado tanto o nome quanto a UF
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where("state", $stateID)
          ;

          break;
        default:
          // Não adiciona nenhum filtro
      }

      // Conclui nossa consulta
      $cities = $CityQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'cityid AS id',
            'name',
            'state',
            'ibgecode',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($cities) > 0) {
        $rowCount = $cities[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $cities
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($stateID))) {
          case 1:
            // Informado apenas o nome
            $error = "Não temos cidades cadastradas cujo nome contém "
              . "<i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas a UF
            $error = "Não temos cidades cadastradas na UF "
              . "<i>{$stateID}</i>."
            ;

            break;
          case 3:
            // Informado tanto o nome quanto o ID da marca
            $error = "Não temos cidades cadastradas na UF "
              . "<i>{$stateID}</i> e cujo nome contém <i>{$name}</i>."
            ;

            break;
          default:
            $error = "Não temos cidades cadastradas.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'cidades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidades. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'cidades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidades. "
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
   * Sincroniza a relação das cidades com o site do STC, fazendo as
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
    $this->info("Processando o sincronismo da relação de cidades "
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

    // Inicializamos o serviço de obtenção das cidades
    $cityService = new CityService($synchronizer, $this->logger,
      $contractor, $DB)
    ;

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $cityService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // cidades
        $cityService->synchronize();
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
   * Recupera a relação das cidades em formato JSON no padrão dos campos
   * de preenchimento automático.
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
    $this->debug("Relação de cidades para preenchimento automático "
      . "despachada."
    );


    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams    = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor    = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $name          = addslashes($postParams['searchTerm']);

    // Determina os limites e parâmetros da consulta
    $start         = 0;
    $length        = $postParams['limit'];
    $ORDER         = 'name ASC';
    
    $this->debug("Acesso aos dados de preenchimento automático das "
      . "cidades que contenham '{name}'",
      [ 'name' => $name ]
    );
    
    try
    {
      // Localiza as cidades na base de dados
      $message = "Cidades cujo nome contém '{$name}'";
      $cities = City::where("contractorid", '=', $contractor->id)
        ->whereRaw("public.unaccented(name) ILIKE "
            . "public.unaccented('%{$name}%')"
          )
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'cityid AS id',
            'name',
            'state'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $cities
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'cidades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidades "
        . "para preenchimento automático. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'cidades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidades "
        . "para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar cidades cujo nome "
            . "contém '$name'",
          'data' => $cities
        ])
    ;
  }
}
