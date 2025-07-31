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
 * O controlador do gerenciamento de clientes cadastrados no sistema
 * STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Cadastre;

use App\Models\STC\Customer;
use App\Providers\STC\STCDataSynchronizer;
use App\Providers\STC\Services\CustomerService;
use Core\Connector\HTTPConnector;
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

class CustomersController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de clientes.
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
    $this->breadcrumb->push('Clientes',
      $this->path('STC\Cadastre\Customers')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de clientes.");

    // Recupera os dados da sessão
    $customer = $this->session->get('customer',
      [ 'searchValue' => '' ])
    ;

    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/customers/customers.twig',
      [ 'customer' => $customer ])
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
    $this->debug("Acesso à relação de clientes.");

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
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('customer',
      [ 'searchValue' => $searchValue ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $CustomerQry = Customer::join('stc.cities',
          function($join) {
            $join->on('customers.cityid', '=', 'cities.cityid');
            $join->on('customers.contractorid',
              '=', 'cities.contractorid'
            );
          })
        ->where('customers.contractorid', '=', $contractor->id)
      ;

      // Verifica se precisa limitar o que estamos exibindo em função
      // das permissões deste usuário
      if ($this->authorization->getUser()->groupid > 5) {
        $CustomerQry->where('customers.customerid', '=',
          $this->authorization->getUser()->entityid);
      }
      
      // Acrescenta os filtros
      if (!empty($searchValue)) {
        $CustomerQry->whereRaw("public.unaccented(customers.name) "
          . "ILIKE public.unaccented(E'%{$searchValue}%')")
        ;
      }

      // Conclui nossa consulta
      $customers = $CustomerQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'customers.clientid AS id',
            'customers.name',
            'cities.name AS cityname',
            'cities.state AS state',
            'customers.nationalregister',
            'customers.status',
            'customers.getpositions',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($customers) > 0) {
        $rowCount = $customers[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $customers
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos clientes cadastrados.";
        } else {
          $error = "Não temos clientes cadastrados cujo nome contém "
            . "<i>{$searchValue}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'clientes',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'clientes',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
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
   * um cliente, sem permitir a sua edição.
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

      // Recupera as informações do cliente
      $customer = Customer::join('stc.cities',
          function($join) {
            $join->on('customers.cityid', '=', 'cities.cityid');
            $join->on('customers.contractorid',
              '=', 'cities.contractorid'
            );
          })
        ->join('entitiestypes', 'customers.entitytypeid', '=',
            'entitiestypes.entitytypeid'
          )
        ->where('customers.contractorid', '=', $contractor->id)
        ->where('customers.clientid', $clientID)
        ->get([
            'cities.name AS cityname',
            'cities.state AS state',
            'entitiestypes.name AS entitytypename',
            'entitiestypes.juridicalperson AS juridicalperson',
            'customers.*'
          ])
        ->toArray()[0]
      ;

      // Registra o acesso
      $this->debug("Processando à visualização do cliente '{name}'.",
        [ 'name' => $customer['name'] ])
      ;

      // Carrega os dados atuais
      $this->validator->setValues($customer);
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o cliente código "
        . "{clientID}.",
        [ 'clientID' => $clientID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este cliente.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Cadastre\Customers' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'STC\Cadastre\Customers');
    }

    // Exibe um formulário para visualização dos dados cadastrais de um
    // cliente

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Clientes',
      $this->path('STC\Cadastre\Customers')
    );
    $this->breadcrumb->push('Visualizar',
      $this->path('STC\Cadastre\Customers\View', [
        'clientID' => $clientID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à visualização dos dados cadastrais do cliente "
      . "'{name}'.",
      [ 'name' => $customer['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/customers/customer.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }

  /**
   * Sincroniza a relação dos clientes com o site do STC, fazendo as
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
    $this->info("Processando o sincronismo da relação de clientes "
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

    // Inicializamos o serviço de obtenção dos clientes
    $customerService = new CustomerService($synchronizer,
      $this->logger, $contractor, $DB)
    ;

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $customerService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // clientes
        $customerService->synchronize();
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
      ->withBody($output);
  }

  /**
   * Alterna o estado da flag indicativa da obtenção do histórico de
   * posicionamentos para os veículos de um cliente.
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
  public function toggleGetPositions(Request $request,
    Response $response, array $args)
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado da obtenção do "
      . "histórico de posicionamentos para os veículos de um cliente."
    );

    // Recupera o ID do cliente
    $clientID = $args['clientID'];

    try
    {
      // Recupera a informação do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações do cliente
      $customer = Customer::where("contractorid", '=', $contractor->id)
        ->where("clientid", '=', intval($clientID))
        ->firstOrFail()
      ;

      // Altera a flag
      $action = $customer->getpositions
        ? "desativado"
        : "ativado"
      ;
      $customer->getpositions = !$customer->getpositions;
      $customer->save();

      $message = "A obtenção do histórico de posicionamentos para os "
        . "veículos do cliente '{$customer->name}' foi {$action} com "
        . "sucesso."
      ;

      // Registra o sucesso
      $this->info($message);

      // Informa que a mudança foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o cliente código "
        . "{clientID} para alternar o seu estado para obtenção do "
        . "histórico de posicionamentos dos seus veículos.",
        [ 'clientID' => $clientID ]
      );

      $message = "Não foi possível localizar o cliente para alternar "
        . "o seu estado para obtenção do histórico de posicionamentos "
        . "dos seus veículos."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da obtenção "
        . "do histórico de posicionamentos dos veículos do cliente "
        . "'{name}'. Erro interno no banco de dados: {error}.",
        [ 'name'  => $customer->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível alternar o estado da obtenção do "
        . "histórico de posicionamentos dos veículos do cliente. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da obtenção "
        . "do histórico de posicionamentos dos veículos do cliente "
        . "'{name}'. Erro interno: {error}.",
        [ 'name'  => $customer->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível alternar o estado da obtenção do "
        . "histórico de posicionamentos dos veículos do cliente. "
        . "Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Recupera a relação dos clientes em formato JSON no padrão dos
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
    $this->debug("Relação de clientes para preenchimento automático "
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
    
    $this->debug("Acesso aos dados de preenchimento automático dos "
      . "clientes que contenham '{name}'",
      [ 'name' => $name ]
    );
    
    try
    {
      // Localiza os clientes na base de dados
      $message = "Clientes cujo nome contém '{$name}'";
      $customers = Customer::where("contractorid", '=', $contractor->id)
        ->whereRaw("public.unaccented(name) ILIKE "
            . "public.unaccented('%{$name}%')"
          )
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'clientid AS id',
            'name',
            'nationalregister'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $customers
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'clientes',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de clientes "
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
        [ 'module' => 'clientes',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de clientes "
        . "para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar clientes cujo nome "
            . "contém '$name'",
          'data' => $customers
        ])
    ;
  }
}
