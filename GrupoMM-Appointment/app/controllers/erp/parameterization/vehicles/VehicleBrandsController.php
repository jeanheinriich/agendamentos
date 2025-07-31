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
 * O controlador do gerenciamento de marcas de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Vehicles;

use App\Models\VehicleBrand;
use App\Models\VehicleType;
use App\Models\VehicleTypePerBrand;
use App\Providers\Fipe\FipeDataSynchronizer;
use App\Providers\Fipe\Services\VehicleBrandService;
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

class VehicleBrandsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de marcas de veículos.
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
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Vehicles\Brands')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de marcas de veículos.");
    
    // Recupera os dados da sessão
    $vehicleBrand = $this->session->get('vehicleBrand',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/vehicles/brands/vehiclebrands.twig',
      [ 'vehicleBrand' => $vehicleBrand ])
    ;
  }
  
  /**
   * Recupera a relação das marcas de veículos em formato JSON.
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
    $this->debug("Acesso à relação de marcas de veículos.");
    
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
    $name = trim($postParams['searchValue']);
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicleBrand',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleBrandQry = VehicleBrand::where('contractorid',
        '=', $this->authorization->getContractor()->id
      );
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $VehicleBrandQry
          ->whereRaw("public.unaccented(vehiclebrands.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $vehicleBrands = $VehicleBrandQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclebrandid AS id',
            'name',
            $this->DB->raw('getVehiclesTypesFromBrandID(contractorid, '
              . 'vehiclebrandid) AS madevehicletypes'
            ),
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicleBrands) > 0) {
        $rowCount = $vehicleBrands[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicleBrands
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos marcas de veículos cadastradas.";
        } else {
          $error = "Não temos marcas de veículos cadastradas cujo "
            . "nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'marcas de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "veículos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'marcas de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "veículos. Erro interno."
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
   * Exibe um formulário para adição de uma marca de veículo, quando
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

    // Recupera as informações dos tipos de veículos cadastrados
    $vehicleTypes = VehicleType::orderBy('vehicletypeid')
      ->get([
          'vehicletypeid AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de marca de veículo.");

      // Monta uma matriz com os tipos de veículos fabricados para
      // conferência
      $typesOfVehicles = [ ];
      foreach ($vehicleTypes as $vehicleType) {
        $typesOfVehicles[] = "{$vehicleType->id}";
      }
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Marca de veículo'),
        'fipename' => V::optional(V::alpha())
          ->setName('Nome no sistema Fipe'),
        'vehicletypes' => V::arrayVal()
          ->each(V::in($typesOfVehicles))
          ->setName('Os tipos de veículos fabricados por esta marca')
      ],
      null,
      [
        'arrayVal' => 'Você deve selecionar um dos tipos de veículos',
        'in' => 'Você deve selecionar um dos tipos de veículos'
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da marca de veículo
          $vehicleBrandData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma marca de veículo com
          // o mesmo nome neste contratante
          if (VehicleBrand::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$vehicleBrandData['name']}')"
                  )
                ->count() === 0) {
            // Grava a nova marca de veículo
            
            // Iniciamos a transação
            $this->DB->beginTransaction();
            
            // Precisa retirar dos parâmetros as informações
            // correspondentes aos tipos de veículos fabricados
            $vehicleTypesData = $vehicleBrandData['vehicletypes'];
            unset($vehicleBrandData['vehicletypes']);

            $vehicleBrand = new VehicleBrand();
            $vehicleBrand->fill($vehicleBrandData);
            // Adiciona o contratante
            $vehicleBrand->contractorid = $contractor->id;
            $vehicleBrand->save();
            $vehicleBrandID = $vehicleBrand->vehiclebrandid;

            // Insere os tipos de veículos fabricados
            foreach($vehicleTypesData AS $vehicleTypeID) {
              // Adiciona o tipo de veículo na relação de tipos de
              // veículos fabricados por marca
              $vehicleTypePerBrand = new VehicleTypePerBrand();
              $vehicleTypePerBrand->contractorid   = $contractor->id;
              $vehicleTypePerBrand->vehicletypeid  = $vehicleTypeID;
              $vehicleTypePerBrand->vehiclebrandid = $vehicleBrandID;
              $vehicleTypePerBrand->fipeid         = 0;
              $vehicleTypePerBrand->save();
            }

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado a marca de veículo '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $vehicleBrandData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A marca de veículo <i>'{name}'"
              . "</i> foi cadastrada com sucesso.",
              [ 'name'  => $vehicleBrandData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Vehicles\Brands' ]
            );
            
            // Redireciona para a página de gerenciamento de marcas de
            // veículos
            return $this->redirect($response,
              'ERP\Parameterization\Vehicles\Brands')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "marca de veículo '{name}' do contratante "
              . "'{contractor}'. Já existe uma marca de veículo com o "
              . "mesmo nome.",
              [ 'name'  => $vehicleBrandData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma marca de veículo "
              . "com o nome <i>'{name}'</i>.",
              [ 'name'  => $vehicleBrandData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "marca de veículo '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $vehicleBrandData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da marca de veículo. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "marca de veículo '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $vehicleBrandData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da marca de veículo. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de uma marca de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Vehicles\Brands')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Vehicles\Brands\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de marca de veículo no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/vehicles/brands/vehiclebrand.twig',
      [ 'formMethod' => 'POST',
        'vehicleTypes' => $vehicleTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma marca de veículo, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações dos tipos de veículos cadastrados
    $vehicleTypes = VehicleType::orderBy('vehicletypeid')
      ->get([
          'vehicletypeid AS id',
          'name'
        ])
    ;
    
    try
    {
      // Recupera as informações da marca de veículo
      $vehicleBrandID = $args['vehicleBrandID'];
      $vehicleBrand = VehicleBrand::where('contractorid',
            '=', $contractor->id
          )
        ->where('vehiclebrandid', '=', $vehicleBrandID)
        ->firstOrFail()
      ;

      // Agora recupera os tipos de veículos fabricados por esta marca
      $manufacturedVehicleTypes = VehicleTypePerBrand::where('contractorid',
            '=', $contractor->id
          )
        ->where('vehiclebrandid', $vehicleBrandID)
        ->get(['vehicletypeid'])
        ->toArray()
      ;
      $vehicleBrand['vehicletypes'] = array_column(
        $manufacturedVehicleTypes, 'vehicletypeid'
      );
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição da marca de veículo "
          . "'{name}' no contratante {contractor}.",
          [ 'name' => $vehicleBrand['name'],
            'contractor' => $contractor->name ]
        );

        // Monta uma matriz com os tipos de veículos fabricados para
        // conferência
        $typesOfVehicles = [ ];
        foreach ($vehicleTypes as $vehicleType) {
          $typesOfVehicles[] = "{$vehicleType->id}";
        }
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Marca de veículo'),
          'fipename' => V::optional(V::notBlank()
              ->length(2, 30))
            ->setName('Nome no sistema Fipe'),
          'vehicletypes' => V::arrayVal()
            ->each(V::in($typesOfVehicles))
            ->setName('Os tipos de veículos fabricados por esta marca')
        ],
        null,
        [
          'arrayVal' => 'Você deve selecionar um dos tipos de veículos',
          'in' => 'Você deve selecionar um dos tipos de veículos'
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da marca de veículo
            $vehicleBrandData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome da marca de
            // veículo
            $save = false;
            if ($vehicleBrand['name'] != $vehicleBrandData['name']) {
              // Modificamos o nome da marca de veículo, então verifica
              // se temos uma marca de veículo com o mesmo nome neste
              // contratante antes de prosseguir
              if (VehicleBrand::where("contractorid",
                        '=', $contractor->id
                      )
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$vehicleBrandData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da marca de veículo '{name}' no "
                  . "contratante '{contractor}'. Já existe uma marca "
                  . "de veículo com o mesmo nome.",
                  [ 'name'  => $vehicleBrandData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma marca de "
                  . "veículo com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da marca de veículo
              
              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos tipos de veículos fabricados
              $vehicleTypesData = $vehicleBrandData['vehicletypes'];
              unset($vehicleBrandData['vehicletypes']);

              // Primeiramente, gravamos os dados da marca
              $vehiclebrand = VehicleBrand::findOrFail($vehicleBrandID);
              $vehiclebrand->fill($vehicleBrandData);
              $vehiclebrand->save();

              // ===================[ Tipos de veículos fabricado ]=====
              // Recupera as informações dos tipos de veículos fabricados
              // e separa os dados para as operações de inserção,
              // atualização e remoção.
              // =======================================================
              
              // -----------------------------[ Pré-processamento ]-----

              // Analisa os tipos de veículos fabricados por esta marca,
              // de forma a separar quais tipos precisam ser adicionados
              // ou removidos

              // Matrizes que armazenarão os dados dos tipos de veículos
              // fabricados a serem adicionados e removidos
              $newVehicleTypes = [ ];
              $delVehicleTypes = [ ];

              // Recupera os tipos de veículos fabricados que estão
              // armazenados atualmente
              $manufacturedVehicleTypes =
                VehicleTypePerBrand::where('contractorid',
                      '=', $contractor->id
                    )
                  ->where('vehiclebrandid', $vehicleBrandID)
                  ->get(['vehicletypeid'])
                  ->toArray()
              ;
              $oldVehicleTypes = array_column(
                  $manufacturedVehicleTypes, 'vehicletypeid'
                )
              ;

              // Verifica quais os tipos de veículos fabricados estavam
              // na base de dados e precisam ser removidos
              $delVehicleTypes = array_diff($oldVehicleTypes,
                $vehicleTypesData)
              ;

              // Verifica quais os tipos de veículos fabricados precisam
              // ser adicionados
              $newVehicleTypes = array_diff($vehicleTypesData,
                $oldVehicleTypes)
              ;
              
              // Primeiramente apaga os tipos de veículos fabricados que
              // foram retirados
              foreach($delVehicleTypes AS $vehicleTypeID) {
                // Remove o tipo de veículo na relação de tipos de
                // veículos fabricados por marca
                $vehicleTypePerBrand =
                  VehicleTypePerBrand::where('contractorid',
                        '=', $contractor->id
                      )
                    ->where('vehiclebrandid', $vehicleBrandID)
                    ->where('vehicletypeid', $vehicleTypeID)
                    ->firstOrFail()
                ;
                $vehicleTypePerBrand->delete();
              }

              // Insere os novos tipos de veículos fabricados
              foreach($newVehicleTypes AS $vehicleTypeID) {
                // Adiciona o tipo de veículo na relação de tipos de
                // veículos fabricados por marca
                $vehicleTypePerBrand = new VehicleTypePerBrand();
                $vehicleTypePerBrand->contractorid   = $contractor->id;
                $vehicleTypePerBrand->vehicletypeid  = $vehicleTypeID;
                $vehicleTypePerBrand->vehiclebrandid = $vehicleBrandID;
                $vehicleTypePerBrand->fipeid         = 0;
                $vehicleTypePerBrand->save();
              }

              // Efetiva a transação
              $this->DB->commit();
              
              // Registra o sucesso
              $this->info("Modificada a marca de veículo '{name}' no "
                . "contratante '{contractor}' com sucesso.",
                [ 'name'  => $vehicleBrandData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A marca de veículo <i>'{name}'"
                . "</i> foi modificada com sucesso.",
                [ 'name'  => $vehicleBrandData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\Vehicles\Brands' ]
              );
              
              // Redireciona para a página de gerenciamento de marcas de
              // veículos
              return $this->redirect($response,
                'ERP\Parameterization\Vehicles\Brands')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações da "
              . "marca de veículo '{name}' no contratante "
              . "'{contractor}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $vehicleBrandData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da marca de veículo. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da marca de veículo '{name}' no contratante "
              . "'{contractor}'. Erro interno: {error}",
              [ 'name'  => $vehicleBrandData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da marca de veículo. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($vehicleBrand->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a marca de veículo "
        . "código {vehicleBrandID}.",
        [ 'vehicleBrandID' => $vehicleBrandID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta marca de "
        . "veículo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Vehicles\Brands' ]
      );
      
      // Redireciona para a página de gerenciamento de marcas de veículos
      return $this->redirect($response,
        'ERP\Parameterization\Vehicles\Brands')
      ;
    }
    
    // Exibe um formulário para edição de uma marca de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Vehicles\Brands')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Vehicles\Brands\Edit', [
        'vehicleBrandID' => $vehicleBrandID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da marca de veículo '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $vehicleBrand['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/vehicles/brands/vehiclebrand.twig',
      [ 'formMethod' => 'PUT',
        'vehicleTypes' => $vehicleTypes ])
    ;
  }
  
  /**
   * Remove a marca de veículo.
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
    $this->debug("Processando à remoção de marca de veículo.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $vehicleBrandID = $args['vehicleBrandID'];

    try
    {
      // Recupera as informações da marca de veículo
      $vehicleBrand = VehicleBrand::where('contractorid',
            '=', $contractor->id
          )
        ->where('vehiclebrandid', '=', $vehicleBrandID)
        ->firstOrFail()
      ;
      
      // Inicia a transação
      $this->DB->beginTransaction();
      
      // Agora apaga a marca de veículo e todos os tipos de veículos
      // fabricados por ela
      $vehicleBrand->deleteCascade();
      
      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("A marca de veículo '{name}' do contratante "
        . "'{contractor}' foi removida com sucesso.",
        [ 'name' => $vehicleBrand->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a marca de veículo "
              . "{$vehicleBrand->name}",
            'data' => 'Delete'
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a marca de veículo "
        . "código {vehicleBrandID} para remoção.",
        [ 'vehicleBrandID' => $vehicleBrandID ]
      );
      
      $message = "Não foi possível localizar a marca de veículo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da marca "
        . "de veículo ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $vehicleBrandID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a marca de veículo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da marca "
        . "de veículo ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $vehicleBrandID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a marca de veículo. Erro "
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
   * Sincroniza a relação das marcas de veículos com o site da Fipe,
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
    $this->info("Processando o sincronismo da relação de marcas "
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

    // Inicializamos o serviço de obtenção das marcas de veículos
    $brandService = new VehicleBrandService($synchronizer,
      $this->logger, $contractor, $DB)
    ;

    // Constrói um manipulador de eventos enviados pelo servidor (SSE)
    // para lidar com o progresso do processamento
    $output = new ServerSentEventHandler(function ()
      use ($serverEvent, $brandService)
    {
      try {
        // Realizamos as requisições para sincronizar as informações de
        // marcas de veículos
        $brandService->synchronize();
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
   * Recupera a relação das marcas de veículos em formato JSON no padrão
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
    $this->debug("Relação de marcas de veículos para preenchimento "
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
    $includeTypePerBrand = false;
    if (isset($postParams['includeTypePerBrand'])) {
      $includeTypePerBrand = ($postParams['includeTypePerBrand']==='true');
    }

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

      $this->debug("Acesso aos dados de preenchimento automático das "
        . "marcas de {vehicleTypes} que contenham '{name}' {$vehicleTypeID}",
        [ 'vehicleTypes' => strtolower($vehicleType->name),
          'name' => $name ]
      );
    } else {
      $this->debug("Acesso aos dados de preenchimento automático das "
        . "marcas de veículos que contenham '{name}'",
        [ 'name' => $name ]
      );
    }
    
    try
    {
      // Localiza as marcas de veículos na base de dados

      // Inicializa a query
      if ($includeTypePerBrand) {
        $vehicleBrandQry = VehicleBrand::join('vehicletypesperbrands',
              'vehiclebrands.vehiclebrandid', '=',
              'vehicletypesperbrands.vehiclebrandid'
            )
          ->where("vehiclebrands.contractorid",
              '=', $contractor->id
            )
          ->whereRaw("public.unaccented(vehiclebrands.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      } else {
        $vehicleBrandQry = VehicleBrand::where("vehiclebrands.contractorid",
              '=', $contractor->id
            )
          ->whereRaw("public.unaccented(vehiclebrands.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      }

      if ($vehicleTypeID > 0) {
        // Precisamos filtrar por tipo de veículo
        $message = "Marcas de " . strtolower($vehicleType->name)
          . " cujo nome contém '{$name}'"
        ;

        // Acrescentamos o filtro por tipo de veículo
        if ($includeTypePerBrand) {
          $vehicleBrandQry
            ->where("vehicletypesperbrands.vehicletypeid",
                '=', $vehicleTypeID
              )
          ;
        } else {
          $vehicleBrandQry
            ->whereRaw(''
                . 'vehiclebrands.vehicleBrandID IN ('
                . '  SELECT vehiclebrandID '
                . '    FROM erp.vehicletypesperbrands'
                . '   WHERE vehicleTypeID = ' . $vehicleTypeID . ')'
              )
          ;
        }
      } else {
        $message = "Marcas de veículos cujo nome contém '{$name}'";
      }

      // Conclui nossa consulta
      if ($includeTypePerBrand) {
        $vehicleBrands = $vehicleBrandQry
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'vehiclebrands.vehiclebrandid AS id',
              'vehiclebrands.name',
              'vehicletypesperbrands.vehicletypeperbrandid'
            ])
        ;
      } else {
        $vehicleBrands = $vehicleBrandQry
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'vehiclebrands.vehiclebrandid AS id',
              'vehiclebrands.name'
            ])
        ;
      }
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $vehicleBrands
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'marcas de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "veículos para preenchimento automático. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'marcas de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "veículos para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => []
        ])
    ;
  }
}
