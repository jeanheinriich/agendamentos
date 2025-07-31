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
 * O controlador do gerenciamento de jornadas de trabalho a serem
 * cumpridas pelos motoristas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Parameterization\Cadastral;

use App\Models\STC\Customer;
use App\Models\STC\Journey;
use App\Models\STC\JourneyPerDay;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class JourneysController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de jornadas de trabalho.
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
    $this->breadcrumb->push('Jornadas',
      $this->path('STC\Parameterization\Cadastral\Journeys')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de jornadas de trabalho.");
    
    // Recupera os dados da sessão
    $journey = $this->session->get('journey',
      [ 'name' => '',
        'customer' => [
          'id' => 0,
          'name'  => ''
        ]
      ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/parameterization/cadastral/journeys/journeys.twig',
      [ 'journey' => $journey ])
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

    if (isset($postParams['draw'])) {
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
    } else {
      // Lida com as informações provenientes de requisição AJAX
      $name = '';
      $orderBy = 'journeys.name';
      $orderDir = 'DESC';
      $start = 0;
      $length = 0;
    }

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

    if (isset($postParams['draw'])) {
      // Seta os valores da última pesquisa na sessão
      $this->session->set('journey',
        [ 'name' => $name,
          'customer' => [
            'id'   => $clientID,
            'name' => $customerName
          ]
        ]
      );
    }

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $JourneyQry = Journey::join('stc.customers',
          function($join) {
            $join->on('journeys.clientid', '=', 'customers.clientid');
            $join->on('journeys.contractorid', '=',
              'customers.contractorid'
            );
          })
        ->where('journeys.contractorid', '=',
            $this->authorization->getContractor()->id
          )
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($clientID))) {
        case 1:
          // Informado apenas o nome
          $JourneyQry
            ->whereRaw("public.unaccented(journeys.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;
          $this->debug("Informado apenas o nome '{$name}'");

          break;
        case 2:
          // Informado apenas o cliente
          $JourneyQry->where("journeys.clientid", $clientID);
          $this->debug("Informado apenas o cliente '[{$clientID}] "
            . "{$customerName}'"
          );

          break;
        case 3:
          // Informado tanto o nome quanto o cliente
          $JourneyQry
            ->whereRaw("public.unaccented(journeys.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where("journeys.clientid", $clientID)
          ;
          $this->debug("Informado tanto o nome '{$name}' quanto o "
            . "cliente '[{$clientID}] {$customerName}'")
          ;

          break;
        default:
          // Não adiciona nenhum filtro
          $this->debug("Não está filtrando");
      }

      if ($length > 0) {
        $JourneyQry
          ->skip($start)
          ->take($length)
        ;
      }

      // Conclui nossa consulta
      if (isset($postParams['draw'])) {
        $journeys = $JourneyQry->orderByRaw($ORDER)
          ->get([
              'journeys.journeyid AS id',
              'customers.name AS customername',
              'journeys.createdat',
              'journeys.name',
              $this->DB->raw(
                "(SELECT sum(days.seconds) "
                . " FROM stc.journeyperday AS days "
                . "WHERE days.journeyid = journeys.journeyid) AS duration"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 0) AS sunday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 1) AS monday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 2) AS tuesday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 3) AS wednesday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 4) AS thursday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 5) AS friday"
              ),
              $this->DB->raw(
                "(SELECT day.seconds "
                . " FROM stc.journeyperday AS day "
                . "WHERE day.journeyid = journeys.journeyid "
                . "  AND day.dayofweek = 6) AS saturday"
              ),
              'journeys.asdefault',
              $this->DB->raw('count(*) OVER() AS fullcount')
            ])
        ;
      } else {
        $journeys = $JourneyQry
          ->orderByRaw($ORDER)
          ->get([
              'journeys.name',
              'journeys.journeyid AS value',
              'journeys.asdefault AS selected'
            ])
        ;
      }
      
      if (count($journeys) > 0) {
        $rowCount = $journeys[0]->fullcount;
        
        if (isset($postParams['draw'])) {
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'draw' => $draw,
                'recordsTotal' => $rowCount,
                'recordsFiltered' => $rowCount,
                'data' => $journeys
              ])
          ;
        } else {
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'OK',
                'params' => $request->getParams(),
                'message' => "Jornadas de trabalho do cliente "
                  . "{$customerName}",
                'data' => $journeys
              ])
          ;
        }
      } else {
        switch ($this->binaryFlags(empty($name), empty($clientID))) {
          case 1:
            // Informado apenas o nome
            $error = "Não temos jornadas de trabalho cadastradas cujo "
              . "nome contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas o cliente
            $error = "Não temos jornadas de trabalho cadastradas no "
              . "cliente <i>{$customerName}</i>."
            ;

            break;
          case 3:
            // Informado tanto o nome quanto o ID do cliente
            $error = "Não temos jornadas de trabalho cadastradas no "
              . "cliente <i>{$customerName}</i> e cujo nome contém "
              . "<i>{$name}</i>."
            ;

            break;
          default:
            $error = "Não temos jornadas de trabalho cadastradas.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'jornadas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de jornadas. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'jornadas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de jornadas. "
        . "Erro interno."
      ;
    }
    
    if (isset($postParams['draw'])) {
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
    } else {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => $error,
            'data' => [ ]
          ])
      ;
    }
  }
  
  /**
   * Exibe um formulário para adição de uma jornada de trabalho, quando
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
      $clientID = 0;
      $customerName = '';
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de jornada de trabalho.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'customername' => V::notBlank()
          ->length(2, 100)
          ->setName('Nome'),
        'clientid' => V::notBlank()
          ->intVal()
          ->setName('ID do cliente'),
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome da jornada'),
        'startdaytime' => V::notBlank()
          ->time('H:i:s')
          ->length(2, 80)
          ->setName('Início do horário diurno'),
        'startnighttime' => V::notBlank()
          ->time('H:i:s')
          ->length(2, 80)
          ->setName('Início do horário noturno'),
        'computeovertime' => V::boolVal()
          ->setName('Informar horas adicionais como horas extras'),
        'discountworkedlesshours' => V::boolVal()
          ->setName('Descontar horas trabalhadas à menos do banco de horas'),
        'asdefault' => V::boolVal()
          ->setName('Esta é a jornada padrão para novos clientes'),
        'days' => [
          'dayofweek' => V::intVal()
            ->between(0, 6)
            ->setName('Dia da semana'),
          'seconds' => V::intVal()
            ->between(0, 86400)
            ->setName('Duração')
        ]
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da jornada de trabalho
          $journeyData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma jornada com o mesmo
          // nome neste cliente deste contratante
          if (Journey::where("contractorid", '=', $contractor->id)
                ->where("clientid", '=', $journeyData['clientid'])
                ->whereRaw("public.unaccented(name) ILIKE "
                  . "public.unaccented('{$journeyData['name']}')")
                ->count() === 0) {
            // Grava a nova jornada de trabalho
            
            // Precisa retirar dos parâmetros as informações
            // correspondentes aos valores de jornada por dia
            $daysData = $journeyData['days'];
            unset($journeyData['days']);

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Grava a nova jornada
            $journey = new Journey();
            $journey->fill($journeyData);
            // Adiciona o contratante e usuários atuais
            $journey->contractorid = $contractor->id;
            $journey->createdbyuserid = $this->authorization
              ->getUser()
              ->userid
            ;
            $journey->updatedbyuserid = $this->authorization
              ->getUser()
              ->userid
            ;
            $journey->save();
            $journeyID = $journey->journeyid;
            
            // Incluímos todos os valores de jornadas diárias nesta
            // jornada
            foreach($daysData AS $dayData) {
              // Retira a ID da jornada no dia, pois estamos inserindo
              unset($dayData['journeyperdayid']);

              // Incluímos um novo valor cobrado deste tipo de contrato
              $journeyPerDay = new JourneyPerDay();
              $journeyPerDay->fill($dayData);
              $journeyPerDay->contractorid = $contractor->id;
              $journeyPerDay->clientid     = $journeyData['clientid'];
              $journeyPerDay->journeyid    = $journeyID;
              $journeyPerDay->save();
            }

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado a jornada de trabalho '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $journeyData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A jornada de trabalho <i>'{name}'"
              . "</i> foi cadastrada com sucesso.",
              [ 'name'  => $journeyData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}", [
              'routeName' => 'STC\Parameterization\Cadastral\Journeys'
            ]);
            
            // Redireciona para a página de gerenciamento de jornadas
            return $this->redirect($response,
              'STC\Parameterization\Cadastral\Journeys')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "jornada de trabalho '{name}' do contratante "
              . "'{contractor}'. Já existe uma jornada com o mesmo "
              . "nome.",
              [ 'name'  => $journeyData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma jornada de "
              . "trabalho com o nome <i>'{name}'</i>.",
              [ 'name'  => $journeyData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "jornada de trabalho '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $journeyData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da jornada de trabalho. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "jornada de trabalho '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $journeyData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da jornada de trabalho. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyJourney = [
        'clientid' => $clientID,
        'customername' => $customerName,
        'startdaytime' => '05:00:00',
        'startnighttime' => '22:00:00',
        'computeovertime' => "true",
        'discountworkedlesshours' => "false",
        'asdefault' => "true",
        'days' => [
          [
            'journeyperdayid' => 0,
            'dayofweek' => 0,
            'seconds' => 0
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 1,
            'seconds' => 28800
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 2,
            'seconds' => 28800
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 3,
            'seconds' => 28800
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 4,
            'seconds' => 28800
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 5,
            'seconds' => 28800
          ],
          [
            'journeyperdayid' => 0,
            'dayofweek' => 6,
            'seconds' => 14400
          ]
        ]
      ];
      $this->validator->setValues($emptyJourney);
    }
    
    // Exibe um formulário para adição de uma jornada
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Cadastral', '');
    $this->breadcrumb->push('Jornadas',
      $this->path('STC\Parameterization\Cadastral\Journeys')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('STC\Parameterization\Cadastral\Journeys\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de jornada de trabalho no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'stc/parameterization/cadastral/journeys/journey.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma jornada de trabalho, quando
   * solicitado, e confirma os dados enviados
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
      // Recupera as informações da jornada de trabalho
      $journeyID = $args['journeyID'];
      $journey = Journey::join('stc.customers',
          function($join) {
            $join->on('journeys.clientid', '=', 'customers.clientid');
            $join->on('journeys.contractorid', '=', 'customers.contractorid');
          })
        ->join('users AS createduser', 'journeys.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'journeys.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('journeys.contractorid', '=', $contractor->id)
        ->where('journeys.journeyid', '=', $journeyID)
        ->firstOrFail([
            'journeys.*',
            'customers.name AS customername',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
        ->toArray()
      ;

      // Agora recupera as informações dos valores de jornada por dia
      // da semana
      $journey['days'] = JourneyPerDay::where('journeyperday.contractorid',
          '=', $contractor->id)
        ->where('journeyperday.journeyid', $journeyID)
        ->get([
          'journeyperday.*'
        ])
        ->toArray()
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da jornada de trabalho "
          . "'{name}' no contratante {contractor}.",
          [ 'name' => $journey['name'],
            'contractor' => $contractor->name ]
        );

        // Valida os dados
        $this->validator->validate($request, [
          'customername' => V::notBlank()
            ->length(2, 100)
            ->setName('Nome'),
          'clientid' => V::notBlank()
            ->intVal()
            ->setName('ID do cliente'),
          'journeyid' => V::notBlank()
            ->intVal()
            ->setName('ID da jornada de trabalho'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome da jornada'),
          'startdaytime' => V::notBlank()
            ->time('H:i:s')
            ->length(2, 80)
            ->setName('Início do horário diurno'),
          'startnighttime' => V::notBlank()
            ->time('H:i:s')
            ->length(2, 80)
            ->setName('Início do horário noturno'),
          'computeovertime' => V::boolVal()
            ->setName('Informar horas adicionais como horas extras'),
          'discountworkedlesshours' => V::boolVal()
            ->setName('Descontar horas trabalhadas à menos do banco de horas'),
          'asdefault' => V::boolVal()
            ->setName('Esta é a jornada padrão para novos clientes'),
          'days' => [
            'journeyperdayid' => V::notBlank()
              ->intVal()
              ->setName('ID do dia da jornada de trabalho'),
            'dayofweek' => V::intVal()
              ->between(0, 6)
              ->setName('Dia da semana'),
            'seconds' => V::intVal()
              ->between(0, 86400)
              ->setName('Duração')
          ]
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados

          // Recupera os dados modificados da jornada de trabalho
          $journeyData = $this->validator->getValues();

          try
          {
            // Primeiro, verifica se não mudamos o nome da jornada de
            // trabalho
            $save = false;
            if ($journey['name'] != $journeyData['name']) {
              // Modificamos o nome da jornada de trabalho, então
              // verifica se temos uma jornada de trabalho com o mesmo
              // nome neste cliente deste contratante antes de prosseguir
              if (Journey::where("contractorid", '=', $contractor->id)
                    ->where("clientid", '=', $journeyData['clientid'])
                    ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$journeyData['name']}')")
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da jornada de trabalho '{name}' no "
                  . "contratante '{contractor}'. Já existe uma jornada "
                  . "de trabalho com o mesmo nome.",
                  [ 'name'  => $journeyData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma jornada de "
                  . "trabalho com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da jornada de trabalho

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos valores de jornada por dia
              $daysData = $journeyData['days'];
              unset($journeyData['days']);

              // Não permite modificar o contratante nem o cliente
              unset($journeyData['contractorid']);
              unset($journeyData['clientid']);

              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Grava as informações da jornada de trabalho
              $journey = Journey::findOrFail($journeyID);
              $journey->fill($journeyData);
              // Adiciona o usuário responsável pela modificação
              $journey->updatedbyuserid = $this->authorization
                ->getUser()
                ->userid
              ;
              $journey->save();
              
              // Por último, modificamos os valores de jornada por dia
              foreach($daysData AS $dayData) {
                // Retira a ID da jornada no dia
                $journeyPerDayID = $dayData['journeyperdayid'];
                unset($dayData['journeyperdayid']);
                
                // Por segurança, nunca permite modificar qual a ID do
                // contratante, nem do cliente ou jornada
                unset($dayData['contractorid']);
                unset($dayData['clientid']);
                unset($dayData['journeyid']);

                $journeyPerDay =
                  JourneyPerDay::findOrFail($journeyPerDayID)
                ;
                $journeyPerDay->fill($dayData);
                $journeyPerDay->save();
              }

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("Modificado a jornada de trabalho '{name}' "
                . "no contratante '{contractor}' com sucesso.",
                [ 'name'  => $journeyData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A jornada de trabalho "
                . "<i>'{name}'</i> foi modificada com sucesso.",
                [ 'name'  => $journeyData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}", [
                'routeName' => 'STC\Parameterization\Cadastral\Journeys'
              ]);
              
              // Redireciona para a página de gerenciamento de jornadas
              return $this->redirect($response,
                'STC\Parameterization\Cadastral\Journeys'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações da "
              . "jornada de trabalho '{name}' no contratante "
              . "'{contractor}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $journeyData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da jornada de trabalho. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da jornada de trabalho '{name}' no contratante "
              . "'{contractor}'. Erro interno: {error}",
              [ 'name'  => $journeyData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da jornada de trabalho. Erro interno."
            );
          }
        } else {
          // Acrescentamos as informações de quem criou e modificou esta
          // jornada de trabalho
          $this->validator->setValue('createdat',
            $journey['createdat']
          );
          $this->validator->setValue('createdbyusername',
            $journey['createdbyusername']
          );
          $this->validator->setValue('updatedat',
            $journey['updatedat']
          );
          $this->validator->setValue('updatedbyusername',
            $journey['updatedbyusername']
          );
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($journey);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a jornada de trabalho "
        . "código {journeyID}.",
        [ 'journeyID' => $journeyID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta jornada "
        . "de trabalho."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'STC\Parameterization\Cadastral\Journeys' ]
      );

      // Redireciona para a página de gerenciamento de jornadas
      return $this->redirect($response,
        'STC\Parameterization\Cadastral\Journeys'
      );
    }

    // Exibe um formulário para edição de uma jornada de trabalho

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Cadastral', '');
    $this->breadcrumb->push('Jornadas',
      $this->path('STC\Parameterization\Cadastral\Journeys')
    );
    $this->breadcrumb->push('Editar',
      $this->path('STC\Parameterization\Cadastral\Journeys\Edit', [
        'journeyID' => $journeyID ])
    );

    // Registra o acesso
    $this->info("Acesso à edição da jornada de trabalho '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $journey['name'],
        'contractor' => $contractor->name ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'stc/parameterization/cadastral/journeys/journey.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }

  /**
   * Remove a jornada de trabalho.
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
    $this->debug("Processando à remoção de jornada de trabalho.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $journeyID = $args['journeyID'];

    try
    {
      // Recupera as informações da jornada de trabalho
      $journey = Journey::where('contractorid', '=', $contractor->id)
        ->where('journeyid', '=', $journeyID)
        ->firstOrFail()
      ;
      
      // Agora apaga a jornada de trabalho

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Agora apaga a jornada de trabalho e os valores relacionados
      $journey->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("A jornada de trabalho '{name}' do contratante "
        . "'{contractor}' foi removida com sucesso.",
        [ 'name' => $journey->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a jornada de trabalho "
              . "{$journey->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a jornada de trabalho "
        . "código {journeyID} para remoção.",
        [ 'journeyID' => $journeyID ]
      );
      
      $message = "Não foi possível localizar a jornada de trabalho "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da jornada "
        . "de trabalho '{name}' no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'name'  => $journey->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a jornada de trabalho. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da jornada "
        . "de trabalho '{name}' no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'name'  => $journey->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a jornada de trabalho. Erro "
        . "interno."
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
   * Alterna a definição de qual jornada de trabalho é a padrão de um
   * cliente em um contratante.
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
  public function toggleDefault(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à definição de jornada de trabalho "
      . "padrão."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $journeyID = $args['journeyID'];
    
    try
    {
      // Recupera as informações da jornada de trabalho
      $journey = Journey::where('contractorid', '=', $contractor->id)
        ->where('journeyid', '=', $journeyID)
        ->firstOrFail()
      ;
      
      // Alterna a definição de jornada padrão
      $action = $journey->asdefault
        ? "deixou de"
        : "passou a"
      ;
      $journey->asdefault = !$journey->asdefault;
      // Adiciona o usuário responsável pela modificação
      $journey->updatedbyuserid = $this->authorization
        ->getUser()
        ->userid
      ;
      $journey->save();
      
      // Registra o sucesso
      $this->info("A jornada de trabalho '{name}' do contratante "
        . "'{contractor}' {action} ser o padrão com sucesso.",
        [ 'name' => $journey->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado da jornada de trabalho padrão
      // foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "A jornada de trabalho {$journey->name} "
              . "{$action} ser o padrão com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a jornada de trabalho "
        . "código {journeyID} no contratante '{contractor}' para "
        . "alternar a definição de jornada padrão.",
        [ 'journeyID' => $journeyID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar a jornada de trabalho "
        . "para alternar a definição de jornada padrão."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar a definição de jornada "
        . "padrão para a jornada de trabalho '{name}' no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'name'  => $journey->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar a definição de jornada "
        . "padrão. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar a definição de jornada "
        . "padrão para a jornada de trabalho '{name}' no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'name'  => $journey->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar a definição de jornada "
        . "padrão. Erro interno."
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
}
