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
 * O controlador do gerenciamento de cidades.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\City;
use App\Models\State;
use App\Providers\ViaCEP\PostalCodeService;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

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
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Cidades',
      $this->path('ADM\Parameterization\Cadastral\Cities')
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
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/cities/cities.twig',
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
      $CityQry = City::join('states', 'cities.state',
          '=','states.state'
        )
      ;

      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($stateID))) {
        case 1:
          // Informado apenas o nome
          $CityQry
            ->whereRaw("public.unaccented(cities.name) "
                . "ILIKE public.unaccented('%{$name}%')"
              )
          ;
          $this->debug("Informado apenas o nome '{$name}'");

          break;
        case 2:
          // Informado apenas a UF
          $CityQry
            ->where("cities.state", $stateID)
          ;
          $this->debug("Informado apenas a UF '{$stateID}'");

          break;
        case 3:
          // Informado tanto o nome quanto a UF
          $CityQry
            ->whereRaw("public.unaccented(cities.name) "
                . "ILIKE public.unaccented('%{$name}%')"
              )
            ->where("cities.state", $stateID)
          ;
          $this->debug("Informado tanto o nome '{$name}' quanto a "
            . "UF '{$stateID}'"
          );

          break;
        default:
          // Não adiciona nenhum filtro
          $this->debug("Não está filtrando");
      }

      // Conclui nossa consulta
      $cities = $CityQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'cityid AS id',
            'cities.name',
            'cities.state',
            'cities.ibgecode',
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
            // Informado tanto o nome quanto a UF da cidade
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
   * Exibe um formulário para adição de uma cidade, quando solicitado,
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
    // Recupera as informações de estados (UFs)
    $states = State::orderBy('state')
      ->get([
          'state AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de cidade.");

      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome da cidade'),
        'state' => V::notBlank()
          ->oneState()
          ->setName('UF'),
        'ibgecode' => V::notBlank()
          ->intVal()
          ->between(1000000, 9900000)->setName('Código IBGE')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da cidade
          $cityData = $this->validator->getValues();

          // Primeiro, verifica se não temos uma cidade com o mesmo nome
          // neste estado (UF)
          if (City::where("state", $cityData['state'])
                ->whereRaw("public.unaccented(name) ILIKE public.unaccented('{$cityData['name']}')")
                ->count() === 0) {
            // Grava a nova cidade
            $city = new City();
            $city->fill($cityData);
            $city->save();

            // Registra o sucesso
            $this->info("Cadastrado a cidade de '{name}' na UF '{uf}' "
              . "com sucesso.",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'] ]
            );

            // Alerta o usuário
            $this->flash("success", "A cidade de <i>'{name}'</i> da UF "
              . "<i>'{uf}'</i> foi cadastrada com sucesso.",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Cadastral\Cities' ]
            );

            // Redireciona para a página de gerenciamento de cidades
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\Cities')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "cidade de '{name}' da UF '{uf}'. Já existe uma cidade "
              . "com o mesmo nome.",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'] ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe uma cidade com o nome "
              . "<i>'{name}'</i> na UF <i>'{name}'</i>.",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "cidade de '{name}' na UF '{uf}'. Erro interno no banco "
            . "de dados: {error}.",
            [ 'name'  => $cityData['name'],
              'uf' => $cityData['state'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da cidade. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "cidade de '{name}' na UF '{uf}'. Erro interno: {error}.",
            [ 'name'  => $cityData['name'],
              'uf' => $cityData['state'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da cidade. Erro interno."
          );
        }
      }
    }

    // Exibe um formulário para adição de uma cidade

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Cidades',
      $this->path('ADM\Parameterization\Cadastral\Cities')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\Cities\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de cidade.");

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/cities/city.twig',
      [ 'formMethod' => 'POST',
        'states' => $states ])
    ;
  }

  /**
   * Exibe um formulário para edição de uma cidade, quando solicitado,
   * e confirma os dados enviados
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
      // Recupera as informações da cidade
      $cityID = $args['cityID'];
      $city = City::findOrFail($cityID);

      // Recupera as informações de estados (UFs)
      $states = State::orderBy('state')
        ->get([
            'state AS id',
            'name'
          ])
      ;

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da cidade de '{name}' na UF "
          . "{uf}.",
          [ 'name' => $city['name'],
            'uf' => $city['state'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome da cidade'),
          'state' => V::notBlank()
            ->oneState()
            ->setName('UF'),
          'ibgecode' => V::notBlank()
            ->intVal()
            ->between(1000000, 9900000)->setName('Código IBGE')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da cidade
            $cityData = $this->validator->getValues();

            // Primeiro, verifica se não mudamos o nome ou UF da cidade
            $save = false;
            if (($city->name != $cityData['name']) ||
                ($city->state != $cityData['state'])) {
              // Modificamos o nome da cidade e/ou da UF, então verifica
              // se temos uma cidade com o mesmo nome nesta UF antes de
              // prosseguir
              if (City::where("state", $cityData['state'])
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$cityData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da cidade de '{name}' na UF '{uf}'. "
                  . "Já existe uma cidade com o mesmo nome.",
                  [ 'name'  => $cityData['name'],
                    'uf' => $cityData['state'] ]
                );

                // Alerta o usuário
                $this->flashNow("error", "Já existe uma cidade com o "
                  . "nome <i>'{name}'</i> na UF <i>'{name}'</i>.",
                  [ 'name'  => $cityData['name'],
                    'uf' => $cityData['state'] ]
                );
              }
            } else {
              $save = true;
            }

            if ($save) {
              // Grava as informações da cidade
              $city->fill($cityData);
              $city->save();

              // Registra o sucesso
              $this->info("Modificada a cidade de '{name}' na UF "
                . "'{uf}' com sucesso.",
                [ 'name'  => $cityData['name'],
                  'uf' => $cityData['state'] ]
              );

              // Alerta o usuário
              $this->flash("success", "A cidade de <i>'{name}'</i> na "
                . "UF <i>'{uf}'</i> foi modificada com sucesso.",
                [ 'name'  => $cityData['name'],
                  'uf' => $cityData['state'] ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\Cities' ]
              );

              // Redireciona para a página de gerenciamento de cidades
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\Cities'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da cidade de '{name}' na UF '{uf}'. Erro interno no "
              . "banco de dados: {error}",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da cidade. Erro interno no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da cidade de '{name}' na UF '{uf}'. Erro interno: "
              . "{error}",
              [ 'name'  => $cityData['name'],
                'uf' => $cityData['state'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da cidade. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($city->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a cidade código "
        . "{cityID}.",
        [ 'cityID' => $cityID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta cidade.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\Cities' ]
      );

      // Redireciona para a página de gerenciamento de cidades
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\Cities'
      );
    }

    // Exibe um formulário para edição de uma cidade

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Cidades',
      $this->path('ADM\Parameterization\Cadastral\Cities')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\Cities\Edit', [
        'cityID' => $cityID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição da cidade de '{name}' da UF '{uf}'.",
      [ 'name' => $city['name'],
        'uf' => $city['state'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/cities/city.twig',
      [ 'formMethod' => 'PUT',
        'states' => $states ])
    ;
  }

  /**
   * Remove a cidade.
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
    $this->debug("Processando à remoção de cidade.");

    // Recupera o ID
    $cityID = $args['cityID'];

    try
    {
      // Recupera as informações da cidade
      $city = City::findOrFail($cityID);

      // Agora apaga a cidade
      $city->delete();

      // Registra o sucesso
      $this->info("A cidade '{name}' da UF '{uf}' foi removida com "
        . "sucesso.",
        [ 'name' => $city->name,
          'uf' => $city->state ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a cidade de {$city->name} na UF "
              . "{$city->state}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a cidade código "
        . "{cityID} para remoção.",
        [ 'cityID' => $cityID ]
      );

      $message = "Não foi possível localizar a cidade para remoção.";
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da cidade "
        . "ID {id}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id'  => $cityID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover a cidade. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da cidade "
        . "ID {id}. Erro interno: {error}.",
        [ 'id'  => $cityID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover a cidade. Erro interno.";
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
   * Recupera o(s) nome(s) de cidade(s) para um campo de preenchimento
   * automático.
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
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $name   = $postParams['searchTerm'];
    $state  = $postParams['state'];

    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = $postParams['limit'];
    $ORDER  = 'name ASC';
    $stateLog = empty($state)?"":" e pertençam ao estado de '$state'";

    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático de "
      . "cidade(s) que contenha(m) '{name}'{stateLog}",
      [ 'name' => $name,
        'stateLog' => $stateLog ]
    );

    try
    {
      // Localiza as cidades na base de dados

      // Inicializa a query
      $CityQry = City::whereRaw("1=1");
      
      // Acrescenta os filtros
      $state = strtoupper($state);
      switch ($this->binaryFlags(empty($name), empty($state))) {
        case 1:
          // Informado apenas o nome da cidade
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas o nome da UF
          $CityQry
            ->where("state", $state)
          ;

          break;
        case 3:
          // Informado tanto o nome da cidade quanto da UF
          $CityQry
            ->whereRaw("public.unaccented(name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where("state", $state)
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
            'ibgecode'
          ])
      ;

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => "Cidades cujo nome contém '$name'$stateLog",
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
        [ 'module' => 'cidade',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidade "
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
        [ 'module' => 'cidade',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cidade "
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
            . "contém '$name'$stateLog",
          'data' => null
        ])
    ;
  }

  /**
   * Recupera as informações do endereço através do CEP
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getPostalCodeData(Request $request,
    Response $response)
  {
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();
    
    // Lida com as informações provenientes do searchbox
    $postalCode = $postParams['postalCode'];

    // Registra o acesso
    $this->debug("Requisitando informações de endereço do CEP "
      . "{postalCode}",
      [ 'postalCode' => $postalCode ]
    );

    // Primeiramente, recuperamos as configurações de integração ao
    // sistema ViaCEP
    $settings = $this->container['settings']['integration']['viacep'];

    // Agora iniciamos o serviço
    $postalCodeService = new PostalCodeService($settings, $this->logger);

    $addressData = $postalCodeService->getAddress($postalCode);

    if (!array_key_exists('error', $addressData)) {
      // Retorna os dados preenchidos
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => 'Endereço obtido através do CEP ' . $postalCode,
            'data' => $addressData
          ])
      ;
    } else {
      // Retorna os dados preenchidos
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => $addressData['message'],
            'data' => null
          ])
      ;
    }
  }
}
