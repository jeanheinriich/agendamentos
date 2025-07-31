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
 * O controlador do gerenciamento de modelos de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Vehicles;

use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Models\VehicleSubtype;
use App\Providers\Fipe\FipeDataSynchronizer;
use App\Providers\Fipe\Services\VehicleModelService;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\HTTP\HTTPService;
use Core\HTTP\Progress\ServerSentEvent;
use Core\Streams\ServerSentEventHandler;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use RuntimeException;
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
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(bool $addition = false): array
  {
    $validationRules = [
      'vehiclemodelid' => V::notBlank()
        ->intVal()
        ->setName('ID do modelo de veículo'),
      'vehicletypeid' => V::intVal()
        ->setName('Tipo de veículo'),
      'vehiclesubtypeid' => V::intVal()
        ->setName('Subtipo de veículo'),
      'vehiclebrandid' => V::intVal()
        ->setName('Marca do veículo'),
      'vehicletypeperbrandid' => V::intVal()
        ->setName('Marca do veículo'),
      'vehiclebrandname' => V::notBlank()
        ->length(2, 30)
        ->setName('Marca do veículo'),
      'name' => V::notBlank()
        ->length(2, 50)
        ->setName('Modelo de veículo'),
      'fipeid' => V::intVal()
        ->min(0, true)
        ->setName('Código Fipe')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['vehiclemodelid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de veículos.
   *
   * @return Collection
   *   A matriz com as informações de tipos de veículos
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de veículos
   */
  protected function getVehicleTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de veículos
      $vehicleTypes = VehicleType::orderBy('vehicletypeid')
        ->get([
            'vehicletypeid AS id',
            'name',
            'singular'
          ])
      ;

      if ( $vehicleTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de veículo "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "veículos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "veículos"
      );
    }

    return $vehicleTypes;
  }

  /**
   * Recupera as informações de subtipos de veículos.
   *
   * @return array
   *   A matriz com as informações de subtipos de veículos
   *
   * @throws RuntimeException
   *   Em caso de não termos subtipos de veículos
   */
  protected function getVehicleSubtypes(): array
  {
    try {
      // Recupera as informações de subtipos de veículos
      $vehicleSubtypes = VehicleSubtype::orderBy('vehicletypeid')
        ->get([
            'vehiclesubtypeid AS id',
            'vehicletypeid',
            'name'
          ])
      ;

      if ( $vehicleSubtypes->isEmpty() ) {
        throw new Exception("Não temos nenhum subtipo de veículo "
          . "cadastrado"
        );
      }

      $subtypesPerType = [];
      foreach ($vehicleSubtypes as $vehicleSubtype) {
        // Criamos o novo subtipo de veículo
        $newVehicleSubtype = [
          'id' => $vehicleSubtype->id,
          'name' => $vehicleSubtype->name
        ];

        if (isset($subtypesPerType[$vehicleSubtype->vehicletypeid])) {
          $subtypesPerType[$vehicleSubtype->vehicletypeid][] = 
            $newVehicleSubtype
          ;
        } else {
          $subtypesPerType[$vehicleSubtype->vehicletypeid] = [
            $newVehicleSubtype
          ];
        }
      }

      foreach ($subtypesPerType as $typeID => $subtypes) {
        if (count($subtypesPerType[$typeID]) !== 1) {
          // Acrescentamos sempre um subtipo não informado
          array_unshift(
            $subtypesPerType[$typeID],
            [
              'id' => 0,
              'name' => 'Não informado'
            ]
          );
        }
      }

      $vehicleSubtypes = $subtypesPerType;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de subtipos "
        . "de veículos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "veículos"
      );
    }

    return $vehicleSubtypes;
  }

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
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Vehicles\Models')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de modelos de veículos.");
    
    // Recupera as informações de tipos de veículos
    $vehicleTypes = VehicleType::orderBy('vehicletypeid')
      ->get([
          'vehicletypeid AS id',
          'name'
        ])
    ;
    
    // Recupera os dados da sessão
    $vehicle = $this->session->get('vehicle',
      [ 'type' => [
          'id' => 0,
          'name' => 'Qualquer tipo'
        ],
        'brand' => [
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
      'erp/parameterization/vehicles/models/vehiclemodels.twig',
      [ 'vehicle' => $vehicle,
        'vehicleTypes' => $vehicleTypes ])
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

    // O ID do contratante
    $contractorID = $this->authorization->getContractor()->id;

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
    $name      = trim($postParams['searchValue']);
    $brandID   = $postParams['brandID'];
    $brandName = $postParams['brandName'];
    $typeID    = $postParams['typeID'];
    $typeName  = $postParams['typeName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicle',
      [ 'type' => [
          'id' => $typeID,
          'name' => $typeName
        ],
        'brand' => [
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
    $typeName = strtolower($typeName);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $vehicleModelQry = VehicleModel::join('vehicletypesperbrands',
            'vehiclemodels.vehicletypeperbrandid',
            '=', 'vehicletypesperbrands.vehicletypeperbrandid'
          )
        ->join('vehiclebrands', 'vehicletypesperbrands.vehiclebrandid',
            '=','vehiclebrands.vehiclebrandid'
          )
        ->join('vehicletypes', 'vehiclemodels.vehicletypeid',
            '=','vehicletypes.vehicletypeid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=','vehiclesubtypes.vehiclesubtypeid'
          )
        ->where('vehiclemodels.contractorid', '=', $contractorID)
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($typeID),
        empty($brandID))) {
        case 1:
          // Informado apenas o nome do modelo de veículo
          $vehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas o tipo de veículo
          $vehicleModelQry
            ->where('vehiclemodels.vehicletypeid', '=', $typeID)
          ;

          break;
        case 3:
          // Informado tanto o nome do modelo de veículo quanto o tipo
          // de veículo
          $vehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
            ->where('vehiclemodels.vehicletypeid', '=', $typeID)
          ;

          break;
        case 4:
          // Informado apenas a marca do veículo
          $vehicleModelQry
            ->where('vehicletypesperbrands.vehiclebrandid', '=',
                $brandID
              )
          ;

          break;
        case 5:
          // Informado tanto o nome do modelo de veículo quanto a marca
          // do veículo
          $vehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
            ->where('vehicletypesperbrands.vehiclebrandid', '=',
                $brandID
              )
          ;
          
          break;
        case 6:
          // Informado tanto a marca do veículo quanto o tipo de veículo
          $vehicleModelQry
            ->where('vehiclemodels.vehicletypeid', '=', $typeID)
            ->where('vehicletypesperbrands.vehiclebrandid',
                '=', $brandID
              )
          ;

          break;
        case 7:
          // Informado tanto o nome do modelo de veículo quanto o tipo
          // de veículo e sua marca
          $vehicleModelQry
            ->whereRaw("public.unaccented(vehiclemodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
            ->where('vehiclemodels.vehicletypeid', '=', $typeID)
            ->where('vehicletypesperbrands.vehiclebrandid',
                '=', $brandID
              )
          ;

          break;
        default:
          // Não adiciona nenhum filtro
      }

      // Conclui nossa consulta
      $vehicleModels = $vehicleModelQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclemodels.vehiclemodelid AS id',
            'vehiclemodels.name',
            'vehiclebrands.name AS vehiclebrandname',
            'vehicletypes.name AS vehicletypename',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 'Não informado'"
              . "  ELSE vehiclesubtypes.name "
              . "END AS vehiclesubtypename"
            ),
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
        switch ($this->binaryFlags(empty($name), empty($typeID),
          empty($brandID))) {
          case 1:
            // Informado apenas o nome do modelo de veículo
            $error = "Não temos modelos de veículos cadastrados cujo "
              . "nome contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas o tipo de veículo
            $error = "Não temos modelos de {$typeName} cadastrados.";

            break;
          case 3:
            // Informado tanto o nome do modelo de veículo quanto o tipo
            // de veículo
            $error = "Não temos modelos de {$typeName} cadastrados "
              . "cujo nome contém <i>{$name}</i>."
            ;

            break;
          case 4:
            // Informado apenas a marca do veículo
            $error = "Não temos modelos de veículos cadastrados da "
              . "marca <i>{$brandName}</i>."
            ;

            break;
          case 5:
            // Informado tanto o nome do modelo de veículo quanto a
            // marca do veículo
            $error = "Não temos modelos de veículos cadastrados da "
              . "marca <i>{$brandName}</i> cujo nome contém "
              . "<i>{$name}</i>."
            ;
            
            break;
          case 6:
            // Informado tanto a marca do veículo quanto o tipo de
            // veículo
            $error = "Não temos modelos de {$typeName} cadastrados "
              . "da marca <i>{$brandName}</i>."
            ;

            break;
          case 7:
            // Informado tanto o nome do modelo de veículo quanto o tipo
            // de veículo e sua marca
            $error = "Não temos modelos de {$typeName} cadastrados "
              . "da marca <i>{$brandName}</i> cujo nome contém "
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
   * Exibe um formulário para adição de um modelo de veículo, quando
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
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehicleTypes();

      // Recupera as informações de subtipos de veículos
      $vehicleSubtypesPerType = $this->getVehicleSubtypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Vehicles\Models' ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect(
        $response,
        'ERP\Parameterization\Vehicles\Models'
      );
    }

    // Recupera as informações de filtragem no gerenciamento para
    // simplificar ao usuário a digitação destas informações
    $typeID    = $request->getQueryParams()['typeID'];
    $typeName  = $request->getQueryParams()['typeName'];
    $brandID   = $request->getQueryParams()['brandID'];
    $brandName = $request->getQueryParams()['brandName'];

    // Converte estes valores para uso nos logs
    $keys   = array_column($vehicleTypes->toArray(), 'id');
    $values = array_column($vehicleTypes->toArray(), 'singular');
    $types  = array_combine($keys, $values);

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de modelo de veículo.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do modelo de veículo
          $vehicleModelData = $this->validator->getValues();

          // Recupera o nome do tipo do veículo
          $typeName = strtolower(
            $types[ $vehicleModelData['vehicletypeid'] ]
          );

          // Primeiro, verifica se não temos um modelo de veículo do
          // mesmo tipo e marca com o mesmo nome neste contratante
          if (VehicleModel::where("contractorid", '=', $contractor->id)
                ->where("vehicletypeid",
                    '=', $vehicleModelData['vehicletypeid']
                  )
                ->where("vehicletypeperbrandid",
                    '=', $vehicleModelData['vehicletypeperbrandid']
                  )
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$vehicleModelData['name']}')"
                  )
                ->count() === 0) {
            // Grava a novo modelo de veículo
            $vehicleModel = new VehicleModel();
            $vehicleModel->fill($vehicleModelData);
            if ($vehicleModel->vehiclesubtypeid == 0) {
              $vehicleModel->vehiclesubtypeid = null;
            }
            // Adiciona o contratante
            $vehicleModel->contractorid = $contractor->id;
            $vehicleModel->save();

            // Registra o sucesso
            $this->info("Cadastrado o modelo de {typeName} '{name}' "
              . "da marca '{brandName}' no contratante '{contractor}' "
              . "com sucesso.",
              [ 'typeName'   => $typeName,
                'name'       => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O modelo de {typeName} <i>'{name}'"
              . "</i> da marca <i>'{brandName}'</i> foi cadastrado com "
              . "sucesso.",
              [ 'typeName'   => $typeName,
                'name'  => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Vehicles\Models' ]
            );
            
            // Redireciona para a página de gerenciamento de modelos de
            // veículos
            return $this->redirect($response,
              'ERP\Parameterization\Vehicles\Models')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "modelo de {typeName} '{name}' da marca '{brandName}' no "
              . "contratante '{contractor}'. Já existe um modelo de "
              . "veículo com o mesmo nome.",
              [ 'typeName'   => $typeName,
                'name'       => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um modelo de "
              . "{typeName} com o nome <i>'{name}'</i> na marca <i>"
              . "'{brandName}'</i>.",
              [ 'typeName'   => $typeName,
                'name'  => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de {typeName} '{name}' da marca '{brandName}' no "
            . "contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}.",
            [
              'typeName'   => $typeName,
              'name'       => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de {typeName} <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno no banco "
            . "de dados.",
            [
              'typeName'   => $typeName,
              'name'  => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname']
            ]
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de {typeName} '{name}' da marca '{brandName}' no "
            . "contratante '{contractor}'. Erro interno: {error}.",
            [
              'typeName'   => $typeName,
              'name'       => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de {typeName} <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno.",
            [
              'typeName'   => $typeName,
              'name'  => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname']
            ]
          );
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      if (!isset($typeID)) {
        $typeID = 1;
      }
      if (!isset($brandID)) {
        $brandID = 0;
        $brandName = '';
      }
      $this->validator->setValues([
        'vehicletypeid' => $typeID,
        'vehiclesubtypeid' => 0,
        'vehiclebrandid' => $brandID,
        'vehiclebrandname' => $brandName,
        'fipeid' => 0
      ]);
    }
    
    // Exibe um formulário para adição de um modelo de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Vehicles\Models')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Vehicles\Models\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de modelo de veículo no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/vehicles/models/vehiclemodel.twig',
      [
        'formMethod' => 'POST',
        'vehicleTypes' => $vehicleTypes,
        'vehicleSubtypesPerType' => $vehicleSubtypesPerType
      ]
    );
  }
  
  /**
   * Exibe um formulário para edição de um modelo de veículo, quando
   * solicitado, e confirma os dados enviados.
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
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehicleTypes();

      // Recupera as informações de subtipos de veículos
      $vehicleSubtypesPerType = $this->getVehicleSubtypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Vehicles\Models' ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect(
        $response,
        'ERP\Parameterization\Vehicles\Models'
      );
    }
    
    try
    {
      // Recupera as informações do modelo de veículo
      $vehicleModelID = $args['vehicleModelID'];
      $vehicleModel = VehicleModel::join('vehicletypesperbrands',
            'vehiclemodels.vehicletypeperbrandid', '=',
            'vehicletypesperbrands.vehicletypeperbrandid'
          )
        ->join('vehiclebrands', 'vehicletypesperbrands.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('vehicletypes', 'vehicletypesperbrands.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=', 'vehiclesubtypes.vehiclesubtypeid'
          )
        ->where('vehiclemodels.contractorid', '=', $contractor->id)
        ->where('vehiclemodelid', '=', $vehicleModelID)
        ->firstOrFail([
            'vehiclemodels.*',
            'vehicletypes.singular AS vehicletypename',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 'Não informado'"
              . "  ELSE vehiclesubtypes.name "
              . "END AS vehiclesubtypename"
            ),
            'vehiclebrands.vehiclebrandid',
            'vehiclebrands.name AS vehiclebrandname',
          ])
      ;

      if ($vehicleModel->vehiclesubtypeid == null) {
        $vehicleModel->vehiclesubtypeid = 0;
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de veículo "
        . "código {vehicleModelID}.",
        [ 'vehicleModelID' => $vehicleModelID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esto modelo de "
        . "veículo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Vehicles\Models' ]
      );
      
      // Redireciona para a página de gerenciamento de modelos de veículos
      return $this->redirect($response,
        'ERP\Parameterization\Vehicles\Models'
      );
    }

    $vehicleTypeName = strtolower($vehicleModel->vehicletypename);

    // Converte estes valores para uso nos logs
    $keys   = array_column($vehicleTypes->toArray(), 'id');
    $values = array_column($vehicleTypes->toArray(), 'singular');
    $types  = array_combine($keys, $values);
    
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do modelo de {typeName} "
        . "'{name}' da marca '{brandName}' no contratante "
        . "'{contractor}'.",
        [
          'typeName'   => $vehicleTypeName,
          'name'       => $vehicleModel->name,
          'brandName'  => $vehicleModel->vehiclebrandname,
          'contractor' => $contractor->name
        ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados modificados do modelo de veículo
          $vehicleModelData = $this->validator->getValues();

          // Recupera o nome do tipo do veículo modificado
          $typeName = strtolower(
              $types[ $vehicleModelData['vehicletypeid'] ]
            )
          ;
          
          // Primeiro, verifica se não mudamos o nome do modelo de
          // veículo
          $save = false;
          if (($vehicleModel->name != $vehicleModelData['name']) ||
              ($vehicleModel->vehiclebrandid != $vehicleModelData['vehiclebrandid'])) {
            // Modificamos o nome do modelo de veículo e/ou sua marca,
            // então verifica se temos um modelo de veículo com o
            // mesmo nome e marca neste contratante antes de prosseguir
            if (VehicleModel::join('vehicletypesperbrands',
                      'vehiclemodels.vehicletypeperbrandid', '=',
                      'vehicletypesperbrands.vehicletypeperbrandid'
                    )
                  ->join('vehiclebrands',
                      'vehicletypesperbrands.vehiclebrandid', '=',
                      'vehiclebrands.vehiclebrandid'
                    )
                  ->where("vehiclemodels.contractorid",
                      '=', $contractor->id
                    )
                  ->whereRaw("public.unaccented(vehiclemodels.name) "
                      . "ILIKE public.unaccented('{$vehicleModelData['name']}')"
                    )
                  ->where("vehiclebrands.vehiclebrandid",
                      '=', $vehicleModelData['vehiclebrandid']
                    )
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as "
                . "informações do modelo de {typeName} '{name}' da "
                . "marca '{brandName}' no contratante "
                . "'{contractor}'. Já existe um modelo de veículo "
                . "com o mesmo nome.",
                [
                  'typeName'   => $typeName,
                  'name'       => $vehicleModelData['name'],
                  'brandName'  => $vehicleModelData['vehiclebrandname'],
                  'contractor' => $contractor->name
                ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe um modelo de "
                . "{typeName} com o nome <i>'{name}'</i> na marca "
                . "<i>'{brandName}'</i>.",
                [
                  'typeName'   => $typeName,
                  'name'  => $vehicleModelData['name'],
                  'brandName'  => $vehicleModelData['vehiclebrandname']
                ]
              );
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Grava as informações do modelo de veículo
            $vehicleModelChanged = VehicleModel::findOrFail($vehicleModelID);
            $vehicleModelChanged->fill($vehicleModelData);
            if ($vehicleModelChanged->vehiclesubtypeid == 0) {
              $vehicleModelChanged->vehiclesubtypeid = null;
            }
            $vehicleModelChanged->save();
            
            // Registra o sucesso
            $this->info("Modificado o modelo de {typeName} '{name}' "
              . "da marca '{brandName}' no contratante '{contractor}' "
              . "com sucesso.",
              [
                'typeName'   => $typeName,
                'name'       => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname'],
                'contractor' => $contractor->name
              ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O modelo de {typeName} <i>"
              . "'{name}'</i> da marca <i>'{brandName}'</i> foi "
              . "modificado com sucesso.",
              [
                'typeName'   => $typeName,
                'name'  => $vehicleModelData['name'],
                'brandName'  => $vehicleModelData['vehiclebrandname']
              ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Vehicles\Models' ]
            );
            
            // Redireciona para a página de gerenciamento de modelos de veículos
            return $this->redirect($response,
              'ERP\Parameterization\Vehicles\Models'
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do modelo de {typeName} '{name}' da marca "
            . "'{brandName}' no contratante '{contractor}'. Erro "
            . "interno no banco de dados: {error}.",
            [
              'typeName'   => $typeName,
              'name'       => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do modelo de {typeName} <i>'{name}'</i> "
            . "no fabricante <i>'{brandName}'</i>.Erro interno no "
            . "banco de dados.",
            [
              'typeName'   => $typeName,
              'name'  => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname']
            ]
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do modelo de {typeName} '{name}' da marca "
            . "'{brandName}' no contratante '{contractor}'. Erro "
            . "interno: {error}.",
            [
              'typeName'   => $typeName,
              'name'       => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do modelo de {typeName} <i>'{name}'</i> "
            . "no fabricante <i>'{brandName}'</i>.Erro interno.",
            [
              'typeName'   => $typeName,
              'name'  => $vehicleModelData['name'],
              'brandName'  => $vehicleModelData['vehiclebrandname']
            ]
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($vehicleModel->toArray());
    }
    
    // Exibe um formulário para edição de um modelo de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Vehicles\Models')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Vehicles\Models\Edit', [
        'vehicleModelID' => $vehicleModelID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do modelo de {typeName} '{name}' "
      . "da marca '{brandName}' no contratante '{contractor}'.",
      [
        'typeName'   => $vehicleTypeName,
        'name'       => $vehicleModel->name,
        'brandName'  => $vehicleModel->vehiclebrandname,
        'contractor' => $contractor->name
      ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/vehicles/models/vehiclemodel.twig',
      [
        'formMethod' => 'PUT',
        'vehicleTypes' => $vehicleTypes,
        'vehicleSubtypesPerType' => $vehicleSubtypesPerType
      ]
    );
  }
  
  /**
   * Remove o modelo de veículo
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
    $this->debug("Processando à remoção de modelo de veículo.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $vehicleModelID = $args['vehicleModelID'];

    try
    {
      // Recupera as informações do modelo de veículo
      $vehicleModel = VehicleModel::where('contractorid',
          '=', $contractor->id)
        ->where('vehiclemodelid', '=', $vehicleModelID)
        ->firstOrFail()
      ;
      
      // Agora apaga o modelo de veículo
      $vehicleModel->delete();
      
      // Registra o sucesso
      $this->info("O modelo de veículo '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $vehicleModel->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o modelo de veículo "
              . "{$vehicleModel->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de veículo "
        . "código {vehicleModelID} para remoção.",
        [ 'vehicleModelID' => $vehicleModelID ]
      );
      
      $message = "Não foi possível localizar o modelo de veículo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de veículo ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $vehicleModelID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de veículo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de veículo ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $vehicleModelID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de veículo. Erro "
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
   * Sincroniza a relação dos modelos de veículos com o site da Fipe,
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
    $this->info("Processando o sincronismo da relação dos modelos "
      . "de veículos com o site da Fipe."
    );
    
    // Recuperamos as configurações de integração ao sistema Fipe
    $settings = $this->container['settings']['integration']['fipe'];
    $url      = $settings['url'];
    $method   = $settings['method'];
    $path     = $settings['path'];

    // Criamos o mecanismo para envio de eventos para o cliente
    $serverEvent = new ServerSentEvent();

    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Criamos um serviço para acesso à API deste provedor através do
    // protocolo HTTP
    $httpService = new HTTPService($url, $method, $path);

    // Criamos nosso sincronizador de dados com este provedor
    $synchronizer = new FipeDataSynchronizer($httpService,
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
  
  /**
   * Recupera a relação dos modelos de veículos em formato JSON no padrão
   * dos campos de preenchimento automático.
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
    $this->debug("Relação de modelos de veículos para preenchimento "
      . "automático despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams    = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor    = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $name          = addslashes($postParams['searchTerm']);
    $vehicleTypeID = intval($postParams['vehicleTypeID']);

    // Determina os limites e parâmetros da consulta
    $start         = 0;
    $length        = $postParams['limit'];
    $ORDER         = 'name ASC';
    
    // Registra o acesso
    if ($vehicleTypeID > 0) {
      // Recupera as informações do tipo de veículo
      $vehicleType = VehicleType::where('vehicletypeid',
            '=', $vehicleTypeID
          )
        ->orderBy('vehicletypeid')
        ->firstOrFail([
            'vehicletypeid AS id',
            'name',
            'singular'
          ])
      ;

      $this->debug("Acesso aos dados de preenchimento automático dos "
        . "modelos de {vehicleTypes} que contenham '{name}'",
        [ 'vehicleTypes' => strtolower($vehicleType->name),
          'name' => $name ]
      );
    } else {
      $this->debug("Acesso aos dados de preenchimento automático dos "
        . "modelos de veículos que contenham '{name}'",
        [ 'name' => $name ]
      );
    }
    
    try
    {
      // Localiza os modelos de veículos na base de dados

      // Verifica se temos mais de um nome
      if ($name == trim($name) && strpos($name, ' ') !== false) {
        // Temos mais de um nome, então separa o primeiro como marca
        // e o segundo como modelo para tentar a busca considerando-se
        // que o primeiro nome é o da marca, sem, todavia, buscar também
        // pelo nome do modelo diretamente
        $parts = explode(' ', trim($name));
        $brandName = array_shift($parts);
        $modelName = trim(implode(" ", $parts));

        $filter = "("
          . "public.unaccented(vehiclemodels.name) ILIKE public.unaccented(E'%{$name}%')"
          . " OR ("
          .   "public.unaccented(vehiclebrands.name) ILIKE public.unaccented(E'%{$brandName}%')"
          .   " AND "
          .   "public.unaccented(vehiclemodels.name) ILIKE public.unaccented(E'%{$modelName}%')"
          . ") )"
        ;
      } else {
        // Faz a busca tradicional apenas pelo nome do modelo
        $filter = "public.unaccented(vehiclemodels.name) ILIKE public.unaccented(E'%{$name}%')";
      }

      // Inicializa a query
      $vehicleModelQry = VehicleModel::join('vehicletypesperbrands',
            'vehiclemodels.vehicletypeperbrandid',
            '=', 'vehicletypesperbrands.vehicletypeperbrandid'
          )
        ->join('vehiclebrands', 'vehicletypesperbrands.vehiclebrandid',
            '=','vehiclebrands.vehiclebrandid'
          )
        ->join('vehicletypes', 'vehiclemodels.vehicletypeid',
            '=','vehicletypes.vehicletypeid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=','vehiclesubtypes.vehiclesubtypeid'
          )
        ->where('vehiclemodels.contractorid', '=', $contractor->id)
        ->whereRaw($filter)
      ;

      if ($vehicleTypeID > 0) {
        // Precisamos filtrar por tipo de veículo
        $message = "Modelos de " . strtolower($vehicleType->name)
          . " cujo nome contém '{$name}'"
        ;

        // Acrescentamos o filtro por tipo de veículo
        $vehicleModelQry
          ->where("vehicletypesperbrands.vehicletypeid",
              '=', $vehicleTypeID
            )
        ;
      } else {
        $message = "Modelos de veículos cujo nome contém '{$name}'";
      }

      // Conclui nossa consulta
      $vehicleModels = $vehicleModelQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclemodels.vehiclemodelid AS id',
            'vehiclemodels.name',
            'vehiclemodels.vehicletypeid',
            'vehicletypes.name AS vehicletypename',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 0"
              . "  ELSE vehiclemodels.vehiclesubtypeid "
              . "END AS vehiclesubtypeid"
            ),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 'Não informado'"
              . "  ELSE vehiclesubtypes.name "
              . "END AS vehiclesubtypename"
            ),
            'vehiclebrands.vehiclebrandid AS vehiclebrandid',
            'vehiclebrands.name AS vehiclebrandname',
            'vehicletypesperbrands.vehicletypeperbrandid'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $vehicleModels
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'modelos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de veículos para preenchimento automático. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'modelos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de veículos para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => null
        ])
    ;
  }
}
