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
 * O controlador do gerenciamento de feriados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization;

use App\Models\GeographicScope;
use App\Models\Holiday;
use App\Models\Month;
use App\Models\State;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class HolidaysController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de feriados.
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
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Feriados',
      $this->path('ADM\Parameterization\Holidays')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de feriados.");

    // Recupera as informações de meses do ano
    $months = Month::get();

    // Recupera os dados da sessão
    $now = new DateTime();
    $holiday = $this->session->get('holiday',
      [ 'year' => $now->format("Y"),
        'city' => [
          'id' => 5346,
          'name'  => 'São Paulo',
          'state' => 'SP'
        ],
        'name'  => '',
        'month' => [
          'id'    => '0',
          'name'  => 'Todos',
          'short' => 'Todos'
        ]
      ])
    ;

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/holidays/holidays.twig',
      [ 'holiday' => $holiday,
        'months'  => $months ])
    ;
  }

  /**
   * Recupera a relação dos feriados em formato JSON.
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
    $this->debug("Acesso à relação de feriados.");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

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
    $year      = $postParams['year'];
    $cityID    = intval($postParams['cityID']);
    $cityName  = $postParams['cityName'];
    $state     = $postParams['state'];
    $name      = $postParams['searchValue'];
    $month     = $postParams['month'];

    $monthNames = Month::getAsArray();

    // Verifica um valor inválido para o ano
    $now = new DateTime();
    $currentYear = $now->format("Y");
    if (is_numeric($year)) {
      $year = intval($year);
      if (!(($year >= 2000) && ($year < 9999))) {
        $year = $currentYear;
      }
    } else {
      $year = $currentYear;
    }

    // Seta os valores da última pesquisa na sessão
    $holiday = $this->session->set('holiday',
      [ 'year' => $year,
        'city' => [
          'id' => $cityID,
          'name'  => $cityName,
          'state' => $state
        ],
        'name'  => $name,
        'month' => [
          'id'    => $month,
          'name'  => $monthNames[$month],
          'short' => ($month>0?mb_substr($monthNames[$month], 0, 3):'Todos'),
        ]
      ])
    ;

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Acrescenta os filtros
      $FILTER = '';
      if (!empty($name))
      {
        $FILTER .= " AND public.unaccented(holidays.name) "
          . "ILIKE public.unaccented('%{$name}%')"
        ;
      }
      if (!empty($month))
      {
        $FILTER .= " AND holidays.month = {$month}";
      }

      $sql = "SELECT holidays.id,
                     holidays.geographicscope,
                     holidays.fulldate,
                     holidays.day,
                     holidays.dayofweekname AS dayofweek,
                     holidays.month,
                     holidays.monthname,
                     holidays.name,
                     holidays.id AS delete,
                     count(*) OVER() AS fullcount
                FROM getHolidaysOnYear('{$year}', {$cityID}) AS holidays
               WHERE (1=1){$FILTER}
               ORDER BY {$ORDER}
               LIMIT $length
              OFFSET $start;";
      $holidays = $this->DB->select($sql);

      if (count($holidays) > 0) {
        $rowCount = $holidays[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $holidays
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($month))) {
          case 1:
            // Informado apenas o nome
            $error = "Não temos feriados cadastrados na cidade de "
              . "{$cityName}/{$state} em {$year} cujo nome contém "
              . "<b>{$name}</b>."
            ;
            
            break;
          case 2:
            // Informado apenas o mês
            $error = "Não temos feriados cadastrados na cidade de "
              . "{$cityName}/{$state} em {$monthNames[$month]} de "
              . "{$year}"
            ;
            
            break;
          case 3:
            // Informado tanto o nome quanto o mês
            $error = "Não temos feriados cadastrados na cidade de "
              . "{$cityName}/{$state} em {$monthNames[$month]} de "
              . "{$year} cujo nome contém <b>{$name}</b>."
            ;
            
            break;
          default:
            // Nenhum parâmetro informado
            $error = "Não temos feriados cadastrados na cidade de "
              . "$cityName em $year"
            ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'feriados',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de feriados. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'feriados',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de feriados. "
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
   * Exibe um formulário para adição de um feriado, quando solicitado,
   * e confirma os dados enviados.
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
    // Recupera as informações de filtragem no gerenciamento para
    // simplificar ao usuário a digitação destas informações
    $cityid   = $request->getQueryParams()['cityid'];
    $cityname = $request->getQueryParams()['cityname'];
    $state    = $request->getQueryParams()['state'];

    // Recupera as informações de abrangência do feriado
    $geographicScopes = GeographicScope::getAsArray();

    // Recupera as informações de estados (UFs)
    $states = State::orderBy('state')
      ->get(['state AS id', 'name'])
    ;

    // Recupera as informações de meses do ano
    $months = Month::getAsList();

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de feriado.");

      // Valida os dados
      $scope = $request->getParam('geographicscope');
      $this->validator->validate($request, [
        'geographicscope' => V::in($geographicScopes)
          ->setName('Abrangência'),
        'cityname' => V::ifThis(
          $scope === 'Nacional',
          V::notEmpty(),
          V::ifThis(
            $scope === 'Estadual',
            V::notEmpty(),
            V::notEmpty()->length(2, 50)
          )
        )->setName('Cidade'),
        'cityid' => V::ifThis(
          $scope === 'Nacional',
          V::equals(0),
          V::ifThis(
            $scope === 'Estadual',
            V::equals(0),
            V::notEmpty()->intVal()
          )
        )->setName('ID da cidade'),
        'state' => V::ifThis(
          $scope === 'Nacional',
          V::not(V::notBlank()),
          V::notBlank()->oneState()
        )->setName('UF'),
        'name' => V::notBlank()
          ->length(2, 100)
          ->setName('Feriado'),
        'day' => V::notBlank()
          ->intVal()
          ->setName('Dia'),
        'month' => V::notBlank()
          ->intVal()
          ->setName('Mês'),
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do feriado
          $holidayData = $this->validator->getValues();

          // Primeiro, verifica se não temos um feriado no mesmo dia
          $save = false;
          switch ($holidayData['geographicscope'])
          {
            case 'Nacional':
              // Feriados com abrangência nacional ignoram a informação
              // de cidade e UF
              $save = (Holiday::where("day", $holidayData['day'])
                ->where("month", $holidayData['month'])
                ->where("geographicscope",
                    $holidayData['geographicscope']
                  )
                ->count() === 0)?true:false
              ;

              // Retira esta informação dos dados a serem gravados
              unset($holidayData['cityid']);
              unset($holidayData['state']);

              break;
            case 'Estadual':
              // Feriados com abrangência estadual ignoram a informação
              // de cidade
              $save = (Holiday::where("day", $holidayData['day'])
                ->where("month", $holidayData['month'])
                ->where("state", $holidayData['state'])
                ->where("geographicscope",
                    $holidayData['geographicscope']
                  )
                ->count() === 0)?true:false
              ;

              // Retira esta informação dos dados a serem gravados
              unset($holidayData['cityid']);

              break;
            default:
              // Feriados com abrangência municipal levam em consideração
              // de cidade e UF
              $save = (Holiday::where("day", $holidayData['day'])
                ->where("month", $holidayData['month'])
                ->where("cityid", $holidayData['cityid'])
                ->where("geographicscope",
                    $holidayData['geographicscope']
                  )
                ->count() === 0)?true:false
              ;
          }

          if ($save) {
            // Grava a novo feriado
            $holiday = new Holiday();
            $holiday->fill($holidayData);
            $holiday->save();

            // Registra o sucesso
            switch ($holidayData['geographicscope'])
            {
              case 'Nacional':
                $this->info("Cadastrado o feriado '{name}' com "
                  . "com sucesso.",
                  [ 'name' => $holidayData['name'] ]
                );

                break;
              case 'Estadual':
                $this->info("Cadastrado o feriado '{name}' no "
                  . "estado de '{uf}' com sucesso.",
                  [ 'name' => $holidayData['name'],
                    'uf'   => $holidayData['state'] ]
                );

                break;
              default:
                $this->info("Cadastrado o feriado '{name}' na "
                  . "cidade de '{city}' da UF '{uf}' com sucesso.",
                  [ 'name' => $holidayData['name'],
                    'city' => $holidayData['cityname'],
                    'uf'   => $holidayData['state'] ]
                );
            }

            // Alerta o usuário
            $this->flash("success", "O feriado <i>{name}</i> foi "
              . "cadastrado com sucesso.",
              [ 'name'  => $holidayData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Holidays' ]
            );

            // Redireciona para a página de gerenciamento de feriados
            return $this->redirect($response,
              'ADM\Parameterization\Holidays'
            );
          } else {
            // Registra o erro
            switch ($holidayData['geographicscope'])
            {
              case 'Nacional':
                $this->debug("Não foi possível inserir as informações "
                  . "do feriado '{name}'. Já existe um feriado na "
                  . "mesma data.",
                  [ 'name' => $holidayData['name'] ]
                );

                break;
              case 'Estadual':
                $this->debug("Não foi possível inserir as informações "
                  . "do feriado '{name}' no estado de '{uf}'. Já "
                  . "existe um feriado na mesma data.",
                  [ 'name' => $holidayData['name'],
                    'uf'   => $holidayData['state'] ]
                );

                break;
              default:
                $this->debug("Não foi possível inserir as informações "
                  . "do feriado '{name}'  na cidade de {city} da UF "
                  . "'{uf}'. Já existe um feriado na mesma data.",
                  [ 'name' => $holidayData['name'],
                    'city' => $holidayData['cityname'],
                    'uf'   => $holidayData['state'] ]
                );
            }

            // Alerta o usuário
            $this->flashNow("error", "Já existe um feriado na mesma "
              . "data."
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "feriado '{name}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name' => $holidayData['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do feriado. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "feriado '{name}'. Erro interno: {error}.",
            [ 'name' => $holidayData['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do feriado. Erro interno."
          );
        }
      }
    } else {
      if (isset($cityid)) {
        // Carrega as informações do feriado selecionada
        $this->validator->setValues([
          'geographicscope' => 'Municipal',
          'cityid' => $cityid,
          'cityname' => $cityname,
          'state' => $state,
          'month' => 1
        ]);
      }
    }

    // Exibe um formulário para adição de um feriado

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Feriados',
      $this->path('ADM\Parameterization\Holidays')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Holidays\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de feriado.");

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/holidays/holiday.twig',
      [ 'formMethod' => 'POST',
        'geographicScopes' => $geographicScopes,
        'states' => $states,
        'months'  => $months ])
    ;
  }

  /**
   * Exibe um formulário para edição de um feriado, quando solicitado,
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
    try
    {
      // Recupera as informações de abrangência do feriado
      $geographicScopes = GeographicScope::getAsArray();

      // Recupera as informações de estados (UFs)
      $states = State::orderBy('state')
        ->get(['state AS id', 'name'])
      ;

      // Recupera as informações de meses do ano
      $months = Month::getAsList();

      // Recupera as informações do feriado
      $holidayID = $args['holidayID'];
      $holiday = Holiday::leftJoin('cities', 'holidays.cityid',
            '=', 'cities.cityid'
          )
        ->where('holidayid', $holidayID)
        ->get([
            'holidays.holidayid',
            'holidays.geographicscope',
            'holidays.name',
            'holidays.month',
            $this->DB->raw('public.MonthName(holidays.month) '
              . 'AS monthname'
            ),
            'holidays.day',
            'holidays.cityid',
            'cities.name AS cityname',
            'holidays.state AS state'
          ])
        ->toArray()[0]
      ;

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        switch ($holiday['geographicscope'])
        {
          case 'Nacional':
            $this->debug("Processando à edição do feriado '{name}'.",
              [ 'name' => $holiday['name'] ]
            );
            break;
          case 'Estadual':
            $this->debug("Processando à edição do feriado '{name}' "
              . "no estado de {uf}.",
              [ 'name' => $holiday['name'],
                'uf' => $holiday['state'] ]
            );

            break;
          default:
            $this->debug("Processando à edição do feriado '{name}' "
              . "na cidade de {city} na UF {uf}.",
              [ 'name' => $holiday['name'],
                'city' => $holiday['cityname'],
                'uf' => $holiday['state'] ]
            );
        }

        // Valida os dados
        $scope = $request->getParam('geographicscope');
        $this->validator->validate($request, [
          'holidayid' => V::notEmpty()
            ->intVal()
            ->setName('ID do feriado'),
          'geographicscope' => V::in($geographicScopes)->setName('Abrangência'),
          'cityname' => V::ifThis(
            $scope === 'Nacional',
            V::notEmpty(),
            V::ifThis(
              $scope === 'Estadual',
              V::notEmpty(),
              V::notEmpty()->length(2, 50)
            )
          )->setName('Cidade'),
          'cityid' => V::ifThis(
            $scope === 'Nacional',
            V::equals(0),
            V::ifThis(
              $scope === 'Estadual',
              V::equals(0),
              V::notEmpty()->intVal()
            )
          )->setName('ID da cidade'),
          'state' => V::ifThis(
            $scope === 'Nacional',
            V::not(V::notBlank()),
            V::notBlank()->oneState()
          )->setName('UF'),
          'name' => V::notBlank()
            ->length(2, 100)
            ->setName('Feriado'),
          'day' => V::notBlank()
            ->intVal()
            ->setName('Dia'),
          'month' => V::notBlank()
            ->intVal()
            ->setName('Mês')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do feriado
            $holidayData = $this->validator->getValues();

            // Primeiro, verifica se não mudamos as informações básicas
            // do feriado (Abrangência, dia, mês, cidade e/ou UF)
            $save = true;
            if (($holiday['geographicscope'] !== $holidayData['geographicscope']) ||
                (($holiday['geographicscope'] === 'Municipal') &&
                 (intval($holiday['cityid']) !== intval($holidayData['cityid']))) ||
                (($holiday['geographicscope'] === 'Estadual') &&
                 ($holiday['state'] !== $holidayData['state'])) ||
                (intval($holiday['day']) !== intval($holidayData['day'])) ||
                (intval($holiday['month']) !== intval($holidayData['month']))) {
              // Modificamos as informações base do feriado, então
              // verifica se temos um feriado nestas mesmas condições
              // antes de prosseguir
              switch ($holidayData['geographicscope'])
              {
                case 'Nacional':
                  // Feriados com abrangência nacional não levam em
                  // consideração a informação de cidade e UF
                  $save = (Holiday::where("day", $holidayData['day'])
                    ->where("month", $holidayData['month'])
                    ->where("geographicscope",
                        $holidayData['geographicscope']
                      )
                    ->count() === 0)?true:false
                  ;

                  break;
                case 'Estadual':
                  // Feriados com abrangência estadual não levam em
                  // consideração a informação de cidade, apenas a UF
                  $save = (Holiday::where("day", $holidayData['day'])
                    ->where("month", $holidayData['month'])
                    ->where("state", $holidayData['state'])
                    ->where("geographicscope",
                        $holidayData['geographicscope']
                      )
                    ->count() === 0)?true:false
                  ;

                  break;
                default:
                  // Feriados com abrangência municipal levam em consideração
                  // de cidade e UF
                  $save = (Holiday::where("day", $holidayData['day'])
                    ->where("month", $holidayData['month'])
                    ->where("cityid", $holidayData['cityid'])
                    ->where("geographicscope",
                        $holidayData['geographicscope']
                      )
                    ->count() === 0)?true:false
                  ;
              }
            }

            if ($save) {
              // Conforme a área de abrangência, retira os campos
              // desnecessários
              switch ($holidayData['geographicscope'])
              {
                case 'Nacional':
                  // Feriados com abrangência nacional não levam em
                  // consideração a informação de cidade e UF, então
                  // retira esta informação dos dados a serem gravados
                  unset($holidayData['cityid']);
                  unset($holidayData['cityname']);
                  unset($holidayData['state']);

                  break;
                case 'Estadual':
                  // Feriados com abrangência estadual não levam em
                  // consideração a informação de cidade, apenas a UF,
                  // então retira esta informação dos dados a serem
                  // gravados
                  unset($holidayData['cityid']);
                  unset($holidayData['cityname']);

                  break;
                default:
                  // Feriados com abrangência municipal utilizam todos
                  // os campos, então não modifica
              }
              // Grava as informações do feriado
              $holidayToChange = Holiday::findOrFail($holidayID);
              $holidayToChange->fill($holidayData);
              $holidayToChange->save();

              // Registra o sucesso
              switch ($holidayData['geographicscope'])
              {
                case 'Nacional':
                  $this->info("Modificado o feriado '{name}' com "
                    . "com sucesso.",
                    [ 'name' => $holidayData['name'] ]
                  );

                  break;
                case 'Estadual':
                  $this->info("Modificado o feriado '{name}' no "
                    . "estado de '{uf}' com sucesso.",
                    [ 'name' => $holidayData['name'],
                      'uf'   => $holidayData['state'] ]
                  );

                  break;
                default:
                  $this->info("Modificado o feriado '{name}' na "
                    . "cidade de '{city}' da UF '{uf}' com sucesso.",
                    [ 'name' => $holidayData['name'],
                      'city' => $holidayData['cityname'],
                      'uf'   => $holidayData['state'] ]
                  );
              }

              $this->flash("success", "O feriado <i>'{name}'</i> foi "
                . "modificado com sucesso.",
                [ 'name'  => $holidayData['name'] ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Holidays' ]
              );

              // Redireciona para a página de gerenciamento de feriados
              return $this->redirect($response,
                'ADM\Parameterization\Holidays')
              ;
            } else {
              // Registra o erro
              switch ($holidayData['geographicscope'])
              {
                case 'Nacional':
                  $this->debug("Não foi possível modificar as "
                    . "informações do feriado '{name}'. Já existe um "
                    . "feriado na mesma data.",
                    [ 'name' => $holidayData['name'] ]
                  );

                  break;
                case 'Estadual':
                  $this->debug("Não foi possível modificar as "
                    . "informações do feriado '{name}' no estado de "
                    . "'{uf}'. Já existe um feriado na mesma data.",
                    [ 'name' => $holidayData['name'],
                      'uf'   => $holidayData['state'] ]
                  );

                  break;
                default:
                  $this->debug("Não foi possível modificar as "
                    . "informações do feriado '{name}'  na cidade de "
                    . "{city} da UF '{uf}'. Já existe um feriado na "
                    . "mesma data.",
                    [ 'name' => $holidayData['name'],
                      'city' => $holidayData['cityname'],
                      'uf'   => $holidayData['state'] ]
                  );
              }

              // Alerta o usuário
              $this->flashNow("error", "Já existe um feriado na mesma "
                . "data."
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do feriado '{name}'. Erro interno no banco de dados: "
              . "{error}.",
              [ 'name' => $holiday['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do feriado. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do feriado '{name}'. Erro interno: {error}.",
              [ 'name' => $holiday['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do feriado. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($holiday);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o feriado código "
        . "{holidayID}.",
        [ 'holidayID' => $holidayID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esto feriado.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Holidays' ]
      );

      // Redireciona para a página de gerenciamento de feriados
      return $this->redirect($response,
        'ADM\Parameterization\Holidays')
      ;
    }

    // Exibe um formulário para edição de um feriado

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Feriados',
      $this->path('ADM\Parameterization\Holidays')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Holidays\Edit', [
        'holidayID' => $holidayID
      ])
    );

    // Registra o acesso
    switch ($holiday['geographicscope'])
    {
      case 'Nacional':
        $this->info("Acesso à edição do feriado '{name}'.",
          [ 'name' => $holiday['name'] ]
        );

        break;
      case 'Estadual':
        $this->info("Acesso à edição do feriado '{name}' no estado "
          . "de '{uf}'.",
          [ 'name' => $holiday['name'],
            'uf'   => $holiday['state'] ]
        );

        break;
      default:
        $this->info("Acesso à edição do feriado '{name}' na cidade "
          . "de '{city}' da UF '{uf}'.",
          [ 'name' => $holiday['name'],
            'city' => $holiday['cityname'],
            'uf'   => $holiday['state'] ]
        );
    }

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/holidays/holiday.twig',
      [ 'formMethod' => 'PUT',
        'geographicScopes' => $geographicScopes,
        'states' => $states,
        'months'  => $months ])
    ;
  }

  /**
   * Remove o feriado.
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
    $this->debug("Processando à remoção de um feriado.");

    // Recupera o ID
    $holidayID = $args['holidayID'];

    try
    {
      // Recupera as informações do feriado
      $holiday = Holiday::findOrFail($holidayID);
      $holidayData = Holiday::leftJoin('cities', 'holidays.cityid',
            '=', 'cities.cityid'
          )
        ->where('holidayid', $holidayID)
        ->get([
            'holidays.geographicscope',
            'holidays.name',
            'cities.name AS cityname',
            'cities.state AS state'
          ])
        ->toArray()[0]
      ;

      // Agora apaga o feriado
      $holiday->delete();

      // Registra o sucesso
      switch ($holidayData['geographicscope'])
      {
        case 'Nacional':
          $this->info("O feriado '{name}' foi removido com sucesso.",
            [ 'name' => $holidayData['name'],
              'uf' => $holidayData['state'] ]
          );

          break;
        case 'Estadual':
          $this->info("O feriado '{name}' no estado de {uf} foi "
            . "removido com sucesso.",
            [ 'name' => $holidayData['name'],
              'uf' => $holidayData['state'] ]
          );

          break;
        default:
          $this->info("O feriado '{name}' na cidade de {city} da "
            . "UF '{uf}' foi removido com sucesso.",
            [ 'name' => $holidayData['name'],
              'city' => $holidayData['cityname'],
              'uf' => $holidayData['state'] ]
          );
      }

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o feriado {$holidayData['name']}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o feriado código "
        . "{holidayID} para remoção.",
        [ 'holidayID' => $holidayID ]
      );

      $message = "Não foi possível localizar o feriado para remoção.";
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "feriado ID {id}. Erro interno no banco de dados: {error}.",
        [ 'id' => $holidayID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o feriado. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "feriado ID {id}. Erro "
        . "interno: {error}.",
        [ 'id' => $holidayID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o feriado. Erro interno.";
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
   * Gera um PDF para impressão da relação de feriados do ano e feriado
   * especificados, bem como de um calendário do ano.
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
    $this->debug("Processando à geração de PDF com as informações "
      . "de feriados."
    );

    // Recupera as informações do contratante
    $params    = $request->getQueryParams();
    $year      = $request->getQueryParams()['year'];
    $cityID    = intval($request->getQueryParams()['cityID']);
    $cityName  = $request->getQueryParams()['cityName'];
    $state     = $request->getQueryParams()['state'];

    // Verifica um valor inválido para o ano
    $now = new DateTime();
    $currentYear = $now->format("Y");
    if (is_numeric($year)) {
      $year = intval($year);
      if (!(($year >= 2000) && ($year < 9999))) {
        $year = $currentYear;
      }
    } else {
      $year = $currentYear;
    }

    $sql = "SELECT holidays.id,
                   holidays.geographicscope,
                   holidays.fulldate,
                   holidays.day,
                   holidays.dayofweekname AS dayofweek,
                   holidays.month,
                   holidays.monthname,
                   holidays.name
              FROM getHolidaysOnYear('{$year}', {$cityID}) AS holidays
             ORDER BY fulldate;"
    ;
    $holidays = (array) $this->DB->select($sql);
    $holidays = json_decode(json_encode($holidays), true);

    // Monta uma tabela simples para determinar os feriados no calendário
    $calendar = [ ];
    $scopeClass = [
      'Nacional'  => 'national',
      'Estadual'  => 'regional',
      'Municipal' => 'local'
    ];
    foreach ($holidays as $holiday) {
      $month = intval($holiday['month']);
      $day   = intval($holiday['day']);
      $calendar[$month][$day] = $scopeClass[ $holiday['geographicscope'] ];
    }
    $content = [
      'year'     => $year,
      'cityName' => $cityName,
      'state'    => $state,
      'holidays' => $holidays,
      'calendar' => $calendar
    ];

    // Renderiza a página para poder converter em PDF
    $title = "Calendário de {$year}";
    if ($cityID > 0) {
      $PDFFileName = "Holidays_On_{$cityName}_{$state}_In_{$year}.pdf";
    } else {
      $PDFFileName = "Holidays_In_{$year}.pdf";
    }
    $page = $this->renderPDF(
      'adm/parameterization/holidays/PDFholidays.twig',
      [ 'content' => $content ]
    );
    $header = $this->renderPDFHeader($title,
      'assets/icons/erp/erp.svg'
    );
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion=true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Controle de feriados');
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

    // Registra o acesso
    if ($cityID > 0) {
      $this->info("Acesso ao PDF com as informações de feriados na "
        . "cidade de '{cityName} - {state}' no ano de '{year}'.",
        [ 'cityName' => $cityName,
          'state'    => $state,
          'year'     => $year ]
      );
    } else {
      $this->info("Acesso ao PDF com as informações de feriados no "
        . "ano de '{year}'.",
        [ 'year'     => $year ]
      );
    }

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT');
  }
}
