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
 * O controlador do gerenciamento de motoristas cadastrados no sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Cadastre;

use App\Models\STC\Customer;
use App\Models\STC\Driver;
use App\Models\STC\Journey;
use App\Models\STC\JourneyPerDriver;
use App\Providers\STC\STCJob;
use App\Providers\STC\Tasks\SendDriverToEquipment;
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

class DriversController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;
  
  /**
   * Exibe a página inicial do gerenciamento de motoristas.
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
    $this->breadcrumb->push('Motoristas',
      $this->path('STC\Cadastre\Drivers')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de motoristas.");
    
    // Recupera os dados da sessão
    $driver = $this->session->get('driver',
      [ 'name' => '',
        'customer' => [
          'id' => 0,
          'name'  => ''
        ],
        'displayStart' => 0
      ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/drivers/drivers.twig',
      [ 'driver' => $driver ])
    ;
  }
  
  /**
   * Recupera a relação dos motoristas em formato JSON.
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
    $this->debug("Acesso à relação de motoristas.");
    
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
    $this->session->set('driver',
      [ 'name' => $name,
        'customer' => [
          'id'   => $clientID,
          'name' => $customerName
        ],
        'displayStart' => $start
      ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $DriverQry = Driver::join('stc.customers',
          function($join) {
            $join->on('drivers.clientid', '=', 'customers.clientid');
            $join->on('drivers.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('drivers.contractorid', '=', $contractor->id)
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($clientID))) {
        case 1:
          // Informado apenas o nome
          $DriverQry
            ->whereRaw("public.unaccented(drivers.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;
          $this->debug("Informado apenas o nome '{$name}'");

          break;
        case 2:
          // Informado apenas o cliente
          $DriverQry
            ->where("drivers.clientid", $clientID)
          ;
          $this->debug("Informado apenas o cliente '[{$clientID}] "
            . "{$customerName}'"
          );

          break;
        case 3:
          // Informado tanto o nome quanto o cliente
          $DriverQry
            ->whereRaw("public.unaccented(drivers.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where("drivers.clientid", $clientID)
          ;
          $this->debug("Informado tanto o nome '{$name}' quanto o "
            . "cliente '[{$clientID}] {$customerName}'")
          ;

          break;
        default:
          // Não adiciona nenhum filtro
          $this->debug("Não está filtrando");
      }

      // Conclui nossa consulta
      $drivers = $DriverQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
          'drivers.driverid AS id',
          'drivers.name',
          'drivers.occupation',
          'drivers.clientid',
          'customers.name AS customername',
          $this->DB->raw("CASE "
            . "WHEN customerismyemployer THEN customers.name "
            . "ELSE drivers.employername "
            . "END AS employername"
          ),
          $this->DB->raw('count(*) OVER() AS fullcount')
        ])
      ;
      
      if (count($drivers) > 0) {
        $rowCount = $drivers[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $drivers
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($clientID))) {
          case 1:
            // Informado apenas o nome
            $error = "Não temos motoristas cadastrados cujo nome "
              . "contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas o cliente
            $error = "Não temos motoristas cadastrados no cliente "
              . "<i>{$customerName}</i>."
            ;

            break;
          case 3:
            // Informado tanto o nome quanto o ID do cliente
            $error = "Não temos motoristas cadastrados no cliente "
              . "<i>{$customerName}</i> e cujo nome contém "
              . "<i>{$name}</i>."
            ;

            break;
          default:
            $error = "Não temos motoristas cadastrados.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'motoristas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "motoristas. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'motoristas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "motoristas. Erro interno."
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
   * Exibe um formulário para adição de um motorista, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(Request $request, Response $response)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid < 6) {
      // Permitimos que a seleção do cliente ocorra dentro do formulário
      $clientID = 0;
      $customerName = '';

      // Não informamos as jornadas pois não sabemos qual o cliente
      $journeys = [ ];
    } else {
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

      // Recupera as informações de jornadas disponíveis para este
      // cliente
      $journeys = Journey::where('contractorid', '=', $contractor->id)
        ->where('clientid', '=', $clientID)
        ->get([
            'journeyid AS id',
            'name'
          ])
        ->toArray()
      ;
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de motorista.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'customername' => V::notBlank()
          ->length(2, 100)
          ->setName('Nome'),
        'clientid' => V::notBlank()
          ->intVal()
          ->setName('ID do cliente'),
        'customerismyemployer' => V::boolVal()
          ->setName('O empregador é a empresa onde está vinculado'),
        'employername' => V::notEmpty()
          ->length(2, 100)
          ->setName('Nome do empregador'),
        'driverid' => V::notBlank()
          ->intVal()
          ->setName('Matrícula'),
        'name' => V::notBlank()
          ->length(2, 100)
          ->setName('Nome do motorista'),
        'occupation' => V::notBlank()
          ->length(2, 100)
          ->setName('Ocupação'),
        'journeyid' => V::notBlank()
          ->intVal()
          ->setName('ID da jornada de trabalho a ser cumprida'),
      ]);

      if ($this->validator->isValid()) {
        $this->debug('Os dados do motorista são VÁLIDOS');

        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da jornada de trabalho
          $driverData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um motorista com o mesmo
          // código neste cliente deste contratante
          if (Driver::where("contractorid", '=', $contractor->id)
               ->where("clientid", '=', $driverData['clientid'])
               ->where("driverid", '=', $driverData['driverid'])
               ->count() === 0) {
            // Precisa retirar dos parâmetros a informação da jornada
            // que este motorista irá cumprir
            $journeyID = $driverData['journeyid'];
            unset($driverData['journeyid']);

            // Retira as informações do empregador, caso o cliente
            // seja o próprio empregador
            if ($driverData['customerismyemployer'] === "true") {
              // Remove os dados do empregador
              $driverData['employername'] = '';
            }

            // Iniciamos a transação
            $this->DB->beginTransaction();
            
            // Grava o novo motorista
            $driver = new Driver();
            $driver->fill($driverData);
            // Adiciona o contratante
            $driver->contractorid = $contractor->id;
            $driver->save();

            // Agora armazenamos a informação da jornada de trabalho que
            // ele irá cumprir
            $journeyPerDriver = new JourneyPerDriver();
            $journeyPerDriver->contractorid = $contractor->id;
            $journeyPerDriver->clientid  = $driverData['clientid'];
            $journeyPerDriver->driverid  = $driverData['driverid'];
            $journeyPerDriver->journeyid = $journeyID;
            $journeyPerDriver->save();

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o motorista '[{id}] {name}' do "
              . "cliente {customer} no contratante '{contractor}' com "
              . "sucesso.",
              [ 'id'  => $driverData['driverid'],
                'name'  => $driverData['name'],
                'customer'  => $driverData['customername'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O motorista <i>[{id}] {name}</i> "
              . "foi cadastrado com sucesso.",
              [ 'id'  => $driverData['driverid'],
                'name'  => $driverData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'STC\Cadastre\Drivers' ]
            );
            
            // Redireciona para a página de gerenciamento de jornadas
            return $this->redirect($response,
              'STC\Cadastre\Drivers')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "motorista '[{id}] {name}' do cliente {customer} no "
              . "contratante '{contractor}'. Já existe um motorista "
              . "com a mesma matrícula.",
              [ 'id'  => $driverData['driverid'],
                'name'  => $driverData['name'],
                'customer' => $driverData['customername'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um motorista com a "
              . " mesma matrícula informada."
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
              . "motorista '[{id}] {name}' do cliente {customer} no "
              . "contratante '{contractor}'. Erro interno no banco de "
              . "dados: {error}.",
            [ 'id'  => $driverData['driverid'],
              'name'  => $driverData['name'],
              'customer' => $driverData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do motorista. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
              . "motorista '[{id}] {name}' do cliente {customer} no "
              . "contratante '{contractor}'. Erro interno: {error}.",
            [ 'id'  => $driverData['driverid'],
              'name'  => $driverData['name'],
              'customer' => $driverData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do motorista. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do motorista são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyDriver = [
        'clientid' => $clientID,
        'customername' => $customerName,
        'customerismyemployer' => true,
        'journeyid' => (count($journeys) > 0)
          ? $journeys[0]['id']
          : 0
      ];
      $this->validator->setValues($emptyDriver);
    }
    
    // Exibe um formulário para adição de um motorista
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Motoristas',
      $this->path('STC\Cadastre\Drivers')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('STC\Cadastre\Drivers\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de motorista no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/drivers/driver.twig',
      [ 'formMethod' => 'POST',
        'journeys' => $journeys ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um motorista, quando solicitado,
   * e confirma os dados enviados.
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
  public function edit(Request $request, Response $response,
    array $args)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    try
    {
      // Recupera as informações do motorista
      $clientID = $args['clientID'];
      $driverID = $args['driverID'];
      $driver = Driver::join('stc.customers',
          function($join) {
            $join->on('drivers.clientid', '=', 'customers.clientid');
            $join->on('drivers.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('drivers.contractorid', '=', $contractor->id)
        ->where('drivers.driverid', $driverID)
        ->where('drivers.clientid', $clientID)
        ->get([
            'customers.name AS customername',
            'drivers.*'
          ])
        ->toArray()[0]
      ;

      // Corrige o nome do empregador, se necessário
      if ($driver['customerismyemployer']) {
        $driver['employername'] = $driver['customername'];
      }

      // Recupera as informações de jornadas que este motorista deve
      // cumprir, com as respectivas datas de início
      $driver['journeysperdriver'] =
        JourneyPerDriver::where('contractorid', '=', $contractor->id)
          ->where('clientid', '=', $clientID)
          ->where('driverid', '=', $driverID)
          ->get([
              'journeyperdriver.*'
            ])
          ->toArray()
      ;
      
      // Recupera as informações de jornadas disponíveis para este
      // cliente
      $journeys = Journey::where('contractorid', '=', $contractor->id)
        ->where('clientid', '=', $clientID)
        ->get([
            'journeyid AS id',
            'name',
            'asdefault'
          ])
        ->toArray()
      ;

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do motorista '[{id}] "
          . "{name}'.",
          [ 'id' => $driverID,
            'name' => $driver['name'] ]
        );

        // Valida os dados
        $this->validator->validate($request, [
          'customername' => V::notBlank()
            ->length(2, 100)
            ->setName('Nome'),
          'clientid' => V::notBlank()
            ->intVal()
            ->setName('ID do cliente'),
          'customerismyemployer' => V::boolVal()
            ->setName('O empregador é a empresa onde está vinculado'),
          'employername' => V::notEmpty()
            ->length(2, 100)
            ->setName('Nome do empregador'),
          'driverid' => V::notBlank()
            ->intVal()
            ->setName('Matrícula'),
          'name' => V::notBlank()
            ->length(2, 100)
            ->setName('Nome do motorista'),
          'occupation' => V::notBlank()
            ->length(2, 100)
            ->setName('Ocupação'),
          'journeysperdriver' => [
            'journeyperdriverid' => V::intVal()
              ->setName('ID da jornada por motorista'),
            'journeyid' => V::notBlank()
              ->intVal()
              ->setName('ID da jornada de trabalho a ser cumprida'),
            'begginingat' => V::date('d/m/Y')
              ->setName('Data de início')
          ]
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados modificados do motorista
          $driverData = $this->validator->getValues();

          try
          {
            // Primeiro, verifica se não mudamos o código (matrícula) do
            // motorista
            $save = false;
            if ($driverID != $driverData['driverid']) {
              // Modificamos a matrícula do motorista, então verifica se
              // não temos um motorista com o mesmo código neste cliente
              // deste contratante
              if (Driver::where("contractorid", '=', $contractor->id)
                   ->where("clientid", '=', $clientID)
                   ->where("driverid", '=', $driverData['driverid'])
                   ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do motorista '[{id}] {name}' no "
                  . "contratante '{contractor}'. Já existe um "
                  . "motorista com a mesma matrícula.",
                  [ 'id' => $driverID,
                    'name' => $driver['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um motorista com a "
                  . "mesma matrícula informada."
                );
              }
            } else {
              $save = true;
            }

            if ($save) {
              // Grava as informações do motorista

              // Precisa retirar dos parâmetros as informações
              // correspondentes às jornadas a serem cumpridas
              $journeysPerDriverData = $driverData['journeysperdriver'];
              unset($driverData['journeysperdriver']);

              // Não permite modificar o contratante nem o cliente
              unset($driverData['contractorid']);
              unset($driverData['clientid']);

              // Retira as informações do empregador, caso o cliente
              // seja o próprio empregador
              if ($driverData['customerismyemployer'] === "true") {
                // Remove os dados do empregador
                $driverData['employername'] = '';
              }

              // Iniciamos a transação
              $this->DB->beginTransaction();

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados das jornadas a serem
              // cumpridas a serem adicionadas, atualizadas e removidas
              $newJourneysPerDriver = [ ];
              $updJourneysPerDriver = [ ];
              $delJourneysPerDriver = [ ];
              
              if ($driverID != $driverData['driverid']) {
                // Como ocorreu a mudança da identificação do motorista,
                // então simplesmente removemos todas as jornadas já
                // inseridas para depois reinserir novamente com a nova
                // identificação
                JourneyPerDriver::where("contractorid", '=', $contractor->id)
                                ->where('clientid', '=', $clientID)
                                ->where('driverid', '=', $driverID)
                                ->delete();

                // Coloca todas as jornadas como novas
                $newJourneysPerDriver = $journeysPerDriverData;
              } else {
                // ====================================[ Jornadas ]=====
                // Recupera as informações das jornadas deste motorista
                // e separa os dados para as operações de inserção,
                // atualização e remoção.
                // =====================================================

                // Analisa as jornadas a serem cumpridas que foram
                // informadas, de forma a separar quais valores precisam
                // ser adicionados, removidos e atualizados
                
                // Os IDs das jornadas a serem cumpridas mantidas para
                // permitir determinar as jornadas a serem removidas
                $heldJourneysPerDriver = [ ];

                // Determina quais jornadas serão mantidas (e atualizadas)
                // e as que precisam ser adicionadas (novas)
                foreach ($journeysPerDriverData AS $journeyPerDriverData) {
                  if (empty($journeyPerDriverData['journeyperdriverid'])) {
                    // Nova jornada a ser cumprida
                    $newJourneysPerDriver[] = $journeyPerDriverData;
                  } else {
                    // Jornada a ser cumprida existente
                    $heldJourneysPerDriver[] = $journeyPerDriverData['journeyperdriverid'];
                    $updJourneysPerDriver[]  = $journeyPerDriverData;
                  }
                }

                // Recupera as jornadas a serem cumpridas armazenadas
                // atualmente
                $actJourneysPerDriver =
                  JourneyPerDriver::where("contractorid", '=',
                        $contractor->id
                      )
                    ->where('clientid', '=', $clientID)
                    ->where('driverid', '=', $driverID)
                    ->get(['journeyperdriverid'])
                    ->toArray()
                ;
                $oldJourneysPerDriver = [ ];
                foreach ($actJourneysPerDriver as $journeyPerDriver) {
                  $oldJourneysPerDriver[] = $journeyPerDriver['journeyperdriverid'];
                }

                // Verifica quais as jornadas a serem cumpridas estavam na
                // base de dados e precisam ser removidas
                $delJourneysPerDriver = array_diff($oldJourneysPerDriver, $heldJourneysPerDriver);
              }

              // Grava as informações do motorista
              $driver = Driver::where('contractorid', '=',
                    $contractor->id
                  )
                ->where('clientid', $clientID)
                ->where('driverid', $driverID)
                ->get()
                ->first()
              ;
              $driver->fill($driverData);
              $driver->save();

              // Primeiro apagamos as jornadas a serem cumpridas
              // removidas pelo usuário durante a edição
              foreach ($delJourneysPerDriver as $journeyPerDriverID) {
                // Apaga cada valor cobrado
                $journeyPerDriver =
                  JourneyPerDriver::findOrFail($journeyPerDriverID)
                ;
                $journeyPerDriver->delete();
              }

              // Agora inserimos as novas jornadas a serem cumpridas
              foreach ($newJourneysPerDriver as $journeyPerDriverData) {
                // Incluímos uma nova jornada a ser cumprida por este
                // motorista
                unset($journeyPerDriverData['journeyperdriverid']);
                $journeyPerDriver = new JourneyPerDriver();
                $journeyPerDriver->fill($journeyPerDriverData);
                $journeyPerDriver->contractorid = $contractor->id;
                $journeyPerDriver->clientid = $clientID;
                $journeyPerDriver->driverid = $driverData['driverid'];
                $journeyPerDriver->save();
              }

              // Por último, modificamos as jornadas a serem cumpridas
              // que foram mantidas
              foreach($updJourneysPerDriver AS $journeyPerDriverData) {
                // Retira a ID do valor cobrado
                $journeyPerDriverID =
                  $journeyPerDriverData['journeyperdriverid']
                ;
                unset($journeyPerDriverData['journeyperdriverid']);
                
                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe nem do contratante, nem do cliente ou
                // do motorista
                unset($journeyPerDriverData['contractorid']);
                unset($journeyPerDriverData['clientid']);
                unset($journeyPerDriverData['driverid']);
                
                // Grava as informações da jornada de trabalho a ser
                // cumprida
                $journeyPerDriver =
                  JourneyPerDriver::findOrFail($journeyPerDriverID)
                ;
                $journeyPerDriver->fill($journeyPerDriverData);
                $journeyPerDriver->save();
              }

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("As informações do motorista '[{id}] {name}' "
                . "do cliente {customer} no contratante '{contractor}' "
                . "foram modificadas com sucesso.",
                [ 'id'  => $driverData['driverid'],
                  'name'  => $driverData['name'],
                  'customer'  => $driver['customername'],
                  'contractor' => $contractor->name ]
              );

              // Alerta o usuário
              $this->flash("success", "O motorista <i>[{id}] "
                . "{name}</i> foi modificado com sucesso.",
                [ 'id' => $driverData['driverid'],
                  'name' => $driverData['name'] ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'STC\Cadastre\Drivers' ]
              );

              // Redireciona para a página de gerenciamento de motoristas
              return $this->redirect($response,
                'STC\Cadastre\Drivers'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do motorista [{id}] {name} do cliente {customer} no "
              . "contratante '{contractor}'. Erro interno no banco de "
              . "dados: {error}",
              [ 'id' => $driverID,
                'name'  => $driver['name'],
                'customer'  => $driver['customername'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do motorista. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do motorista [{id}] {name} do cliente {customer} no "
              . "contratante '{contractor}'. Erro interno: {error}",
              [ 'id' => $driverID,
                'name'  => $driver['name'],
                'customer'  => $driver['customername'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do motorista. Erro interno."
            );
          }
        } else {
          // Recupera os dados modificados do cliente
          $driverData = $this->validator->getValues();

          // Junta com os demais dados do cliente
          $driverData = array_merge($driver, $driverData);

          // Carrega os dados atuais
          $this->validator->setValues($driverData);
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($driver);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o motorista código "
        . "{id}.",
        [ 'id' => $driverID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "motorista."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Cadastre\Drivers' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'STC\Cadastre\Drivers');
    }

    // Exibe um formulário para edição de um cliente

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Motoristas',
      $this->path('STC\Cadastre\Drivers')
    );
    $this->breadcrumb->push('Editar',
      $this->path('STC\Cadastre\Drivers\Edit', [
        'clientID' => $clientID,
        'driverID' => $driverID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do motorista [{id}] {name}.",
      [ 'id' => $driverID,
        'name'  => $driver['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/cadastre/drivers/driver.twig',
      [ 'formMethod' => 'PUT',
        'driverID' => $driverID,
        'journeys' => $journeys ])
    ;
  }
  
  /**
   * Remove o motorista.
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
  public function delete(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à remoção de motorista.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do motorista
    $clientID = $args['clientID'];
    $driverID = $args['driverID'];

    try
    {
      // Recupera as informações do motorista que estamos removendo
      $driver = Driver::join('stc.customers',
          function($join) {
            $join->on('drivers.clientid', '=',
              'customers.clientid'
            );
            $join->on('drivers.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('drivers.contractorid', '=', $contractor->id)
        ->where('drivers.driverid', $driverID)
        ->where('drivers.clientid', $clientID)
        ->get([
            'customers.name AS customername',
            'drivers.*'
          ])
        ->first()
      ;

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Primeiramente apagamos as jornadas cumpridas por este motorista
      JourneyPerDriver::where("contractorid", '=', $contractor->id)
        ->where('clientid', '=', $clientID)
        ->where('driverid', '=', $driverID)
        ->delete()
      ;

      // Agora apagamos as informações do motorista
      Driver::where('contractorid', '=', $contractor->id)
        ->where('clientid', '=', $clientID)
        ->where('driverid', '=', $driverID)
        ->delete()
      ;
      
      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O motorista '[{id}] {name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'id' => $driverID,
          'name' => $driver->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o motorista {$driver->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o motorista código {id} "
        . "para remoção.",
        [ 'id' => $driverID ]
      );
      
      $message = "Não foi possível localizar o motorista para remoção.";
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "motorista '[id] {name}' no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $driverID,
          'name'  => $driver->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o motorista. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "motorista '[id] {name}' no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $driverID,
          'name'  => $driver->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o motorista. Erro interno.";
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
   * Recupera a relação dos motoristas em formato JSON no padrão dos
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
    $this->debug("Relação de motoristas para preenchimento automático "
      . "despachada."
    );


    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams    = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor    = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $name          = addslashes($postParams['searchTerm']);
    $clientID      = $postParams['clientID'];

    // Determina os limites e parâmetros da consulta
    $start         = 0;
    $length        = $postParams['limit'];
    $ORDER         = 'name ASC';
    
    $this->debug("Acesso aos dados de preenchimento automático dos "
      . "motoristas que contenham '{name}' e sejam do cliente "
      . "'{clientid}",
      [ 'name' => $name,
        'clientid' => $clientID ]
    );
    
    try
    {
      // Localiza os motoristas na base de dados
      $message = "Motoristas cujo nome contém '{$name}'";
      $drivers = Driver::join('stc.customers',
          function($join) {
            $join->on('drivers.clientid', '=', 'customers.clientid');
            $join->on('drivers.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->whereRaw("public.unaccented(drivers.name) ILIKE "
            . "public.unaccented('%{$name}%')"
          )
        ->where("drivers.contractorid", '=', $contractor->id)
        ->where("drivers.clientid", '=', $clientID)
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'drivers.driverid AS id',
            'drivers.clientid',
            'drivers.name',
            'drivers.occupation',
            'drivers.clientid',
            'customers.name AS customername'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $drivers
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
            . "nome contém '$name'",
          'data' => null
        ])
    ;
  }

  /**
   * Sincroniza a informação de um motorista com um ou mais teclados
   * instalados nos veículos através do site da STC.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function sendDriver(Request $request, Response $response)
  {
    // Recupera os dados da requisição
    $getParams = $request->getQueryParams();

    // Recupera a ID do cliente para o qual faremos o envio
    $clientID = $getParams['clientID'];

    // Recupera a ID do equipamento (se for apenas um) para o qual
    // faremos o envio
    $deviceID = $getParams['deviceID'];
    $plate    = $getParams['plate'];

    // Recuperamos a ID do motorista a ser enviada
    $driverID = $getParams['driverID'];

    // Recupera a ID do motorista a ser enviada

    // Registra o acesso
    if ($deviceID > 0) {
      $this->info("Processando o envio da ID de motorista {driverID} "
        . "para o teclado acoplado ao equipamento '{deviceID}' "
        . "instalado no veículo placa {plate}.", [
        'driverID' => $driverID,
        'deviceID' => $deviceID,
        'plate' => $plate ]
      );
    } else {
      $this->info("Processando o envio da ID de motorista {driverID} "
        . "para todos os teclados acoplados à equipamentos instalados "
        . "em veículos do cliente ID {clientID}.", [
        'driverID' => $driverID,
        'clientID' => $clientID ]
      );
    }

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
    // 1. Envio da ID's do motorista
    $sendDriver = new SendDriverToEquipment($this->logger);
    $sendDriver->setDriver($driverID);
    $deviceJob->addTask($sendDriver);

    // Passamos a chave de conexão
    $deviceJob->setKey($contractor->stckey);

    // Passamos os ID's de dispositivos
    $devices = $this->getDevices($clientID, $deviceID);
    $deviceJob->setDevices($devices);

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
   * Recupera as informações de IDs de dispositivos para o cliente no
   * qual o motorista está vinculado.
   *
   * @param int $clientID
   *   A ID do cliente
   * @param int $vehicleID
   *   A ID de um veículo, nos casos em que se deseja enviar informações
   *   apenas para este veículo (opcional)
   *
   * @return array
   *   A matriz com as informações de dispositivos
   */
  protected function getDevices(int $clientID,
    int $deviceID = 0): array
  {
    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    try {
      $this->debug("Recuperando as informações de dispositivos para o "
        . "cliente {clientID}",
        [ 'clientID'  => $clientID ]
      );

      $devices = Customer::join("stc.vehicles", function($join) {
            $join->on("customers.clientid", '=', "vehicles.clientid");
            $join->on("customers.contractorid", '=', "vehicles.contractorid");
          })
        ->join("stc.devices", function($join) {
            $join->on("vehicles.deviceid", '=', "devices.deviceid");
            $join->on("vehicles.contractorid", '=', "devices.contractorid");
          })
        ->join("stc.devicemodels", function($join) {
            $join->on("devices.devicemodelid", '=', "devicemodels.devicemodelid");
            $join->on("devices.contractorid", '=', "devicemodels.contractorid");
          })
        ->where("customers.contractorid", '=', $contractor->id)
        ->where("customers.status", "true")
        ->where("customers.getpositions", "true")
        ->where("vehicles.status", "true")
        ->where("customers.clientid", $clientID)
        ->when($deviceID > 0, function($query) use ($deviceID) {
            return $query->where("devices.deviceid", '=', $deviceID);
          })
        ->where("devicemodels.abletokeyboard", "true")
        ->orderBy("customers.name", "desc")
        ->orderBy("vehicles.plate", "desc")
        ->get([
            'customers.clientid',
            'customers.name AS customername',
            'vehicles.plate',
            'vehicles.deviceid'
          ])
        ->toArray()
      ;

      $this->debug("Solicitada as informações de motoristas para o "
        . "cliente {clientID} e retornado {count}",
        [ 'clientID'  => $clientID,
          'count' => count($devices) ]
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

    if ($devices) {
      return array_column($devices, 'deviceid');
    }

    return [];
  }
}
