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
 * O controlador do gerenciamento de subtipos de um determinado tipo de
 * veículo. Um subtipo é usado para definir melhor o ícone a ser exibido
 * no mapa.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Vehicles;

use App\Models\VehicleSubtype;
use App\Models\VehicleType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class VehicleSubtypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de subtipos de um tipo de
   * veículo.
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
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Subtipos',
      $this->path('ADM\Parameterization\Vehicles\Subtypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de subtipos de um tipo de "
      . "veículo."
    );
    
    // Recupera as informações de tipos de veículos
    $vehicleTypes = VehicleType::orderBy('vehicletypeid')
      ->get([
          'vehicletypeid AS id',
          'name'
        ])
    ;
    
    // Recupera os dados da sessão
    $vehicleSubtype = $this->session->get('vehiclesubtype',
      [ 'type' => [
          'id' => 0,
          'name' => 'Qualquer tipo'
        ],
        'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/subtypes/vehiclesubtypes.twig',
      [ 'vehiclesubtype' => $vehicleSubtype,
        'vehicleTypes' => $vehicleTypes ])
    ;
  }
  
  /**
   * Recupera a relação dos subtipos de um tipo de veículo em formato
   * JSON.
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
    $this->debug("Acesso à relação de subtipos de um tipo de veículo.");
    
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
    $name     = $postParams['searchValue'];
    $typeID   = $postParams['typeID'];
    $typeName = $postParams['typeName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehiclesubtype',
      [ 'type' => [
          'id' => $typeID,
          'name' => $typeName
        ],
        'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    $typeName = strtolower($typeName);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleSubtypeQry = VehicleSubtype::join('vehicletypes',
          'vehiclesubtypes.vehicletypeid', '=',
          'vehicletypes.vehicletypeid'
        )
      ;
      
      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($typeID))) {
        case 1:
          // Informado apenas o nome do subtipo de veículo
          $VehicleSubtypeQry
            ->whereRaw("public.unaccented(vehiclesubtypes.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas o tipo de veículo
          $VehicleSubtypeQry
            ->where('vehiclesubtypes.vehicletypeid', '=', $typeID)
          ;

          break;
        case 3:
          // Informado tanto o nome do subtipo de veículo quanto o tipo
          // de veículo
          $VehicleSubtypeQry
            ->whereRaw("public.unaccented(vehiclesubtypes.name) ILIKE "
                . "public.unaccented('%{$name}%')"
              )
            ->where('vehiclesubtypes.vehicletypeid', '=', $typeID)
          ;

          break;
        default:
          // Não adiciona nenhum filtro
      }

      // Conclui nossa consulta
      $vehicleSubtypes = $VehicleSubtypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclesubtypes.vehiclesubtypeid AS id',
            'vehiclesubtypes.vehicletypeid',
            'vehicletypes.name AS vehicletypename',
            'vehiclesubtypes.name',
            'vehiclesubtypes.symbol',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicleSubtypes) > 0) {
        $rowCount = $vehicleSubtypes[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicleSubtypes
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($typeID),
          empty($brandID))) {
          case 1:
            // Informado apenas o nome do subtipo de veículo
            $error = "Não temos subtipos de veículos cadastrados cujo "
              . "nome contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas o tipo de veículo
            $error = "Não temos subtipos de {$typeName} cadastrados.";

            break;
          case 3:
            // Informado tanto o nome do subtipo de veículo quanto o
            // tipo de veículo
            $error = "Não temos subtipos de {$typeName} cadastrados "
              . "cujo nome contém <i>{$name}</i>."
            ;

            break;
          default:
            $error = "Não temos subtipos de veículos cadastrados.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'subtipos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de subtipos "
        . "de veículos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'subtipos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de subtipos "
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
   * Exibe um formulário para adição de um subtipo de veículo, quando
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
    // Recupera as informações de filtragem no gerenciamento para
    // simplificar ao usuário a digitação destas informações
    $typeID    = $request->getQueryParams()['typeID'];
    $typeName  = $request->getQueryParams()['typeName'];

    // Recupera as informações dos tipos de veículos cadastrados
    $vehicleTypes = VehicleType::orderBy('vehicletypeid')
      ->get([
          'vehicletypeid AS id',
          'name',
          'singular'
        ])
    ;

    // Converte estes valores para uso nos logs
    $keys   = array_column($vehicleTypes->toArray(), 'id');
    $values = array_column($vehicleTypes->toArray(), 'singular');
    $types  = array_combine($keys, $values);

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de um subtipo de um tipo de "
        . "veículo."
      );
      
      // Valida os dados
      $this->validator->validate($request, [
        'vehicletypeid' => V::intVal()
          ->setName('Tipo de veículo'),
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Subtipo de veículo')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de veículo
          $vehicleSubtypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um subtipo do tipo de
          // veículo com o mesmo nome
          if (VehicleSubtype::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$vehicleSubtypeData['name']}')"
                  )
                ->where("vehicletypeid",
                    '=', $vehicleSubtypeData['vehicletypeid']
                  )
                ->count() === 0) {
            // Grava o novo subtipo de veículo
            $vehicleSubtype = new VehicleSubtype();
            $vehicleSubtype->fill($vehicleSubtypeData);
            $vehicleSubtype->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o subtipo de {typeName} '{name}' "
              . "com sucesso.",
              [ 'typeName' => $typeName,
                'name' => $vehicleSubtypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O subtipo de {typeName} "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'typeName'   => $typeName,
                'name'  => $vehicleSubtypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Vehicles\Subtypes' ]
            );
            
            // Redireciona para a página de gerenciamento de subtipos de
            // veículos
            return $this->redirect($response,
              'ADM\Parameterization\Vehicles\Subtypes'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "subtipo de {typeName} '{name}'. Já existe um subtipo "
              . "com o mesmo nome.",
              [ 'typeName' => $typeName,
                'name' => $vehicleSubtypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um subtipo de "
              . "{typeName} com o nome <i>'{name}'</i>.",
              [ 'typeName'   => $typeName,
                'name'  => $vehicleSubtypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "subtipo de {typeName} '{name}'. Erro interno no banco "
            . "de dados: {error}.",
            [ 'typeName' => $typeName,
              'name' => $vehicleSubtypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do subtipo de {typeName} <i>'{name}'</i>. "
            . "Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "subtipo de {typeName} '{name}'. Erro interno: {error}.",
            [ 'typeName' => $typeName,
              'name' => $vehicleSubtypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do subtipo de {typeName} <i>'{name}'</i>. "
            . "Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      if (!isset($typeID)) {
        $typeID = 1;
      }
      $this->validator->setValues([
        'vehicletypeid' => $typeID
      ]);
    }
    
    // Exibe um formulário para adição de um subtipo de um tipo de
    // veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Subtipos',
      $this->path('ADM\Parameterization\Vehicles\Subtypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Vehicles\Subtypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de um subtipo de tipo de veículo.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/subtypes/vehiclesubtype.twig',
      [ 'formMethod' => 'POST',
        'vehicleTypes' => $vehicleTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um subtipo de um tipo de
   * veículo, quando solicitado, e confirma os dados enviados.
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
      // Recupera as informações do tipo de veículo
      $vehicleSubtypeID = $args['vehicleSubtypeID'];
      $vehicleSubtype = VehicleSubtype::join('vehicletypes',
            'vehiclesubtypes.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->where('vehiclesubtypeid', '=', $vehicleSubtypeID)
        ->firstOrFail([
            'vehiclesubtypes.*',
            'vehicletypes.singular AS vehicletypename',
          ])
      ;

      // Recupera as informações dos tipos de veículos cadastrados
      $vehicleTypes = VehicleType::orderBy('vehicletypeid')
        ->get([
            'vehicletypeid AS id',
            'name',
            'singular'
          ])
      ;
      $vehicleTypeName = strtolower($vehicleTypes->name);

      // Converte estes valores para uso nos logs
      $keys   = array_column($vehicleTypes->toArray(), 'id');
      $values = array_column($vehicleTypes->toArray(), 'singular');
      $types  = array_combine($keys, $values);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do subtipo de {typeName} "
          . "'{name}'.",
          [ 'typeName' => $vehicleTypeName,
            'name' => $vehicleSubtype->name ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'vehicletypeid' => V::intVal()
            ->setName('Tipo de veículo'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de veículo')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do subtipo de veículo
            $vehicleSubtypeData = $this->validator->getValues();

            // Recupera o nome do tipo do veículo modificado
            $typeName = strtolower(
                $types[ $vehicleSubtypeData['vehicletypeid'] ]
              )
            ;
            
            $save = false;

            // Primeiro, verifica se não mudamos o tipo de veículo e/ou
            // o nome do subtipo
            if ( ($vehicleSubtypeData['vehicletypeid'] !== $vehicleSubtype->vehicletypeid)
                 || ($vehicleSubtypeData['name'] !== $vehicleSubtype->name) ) {
              // Modificamos o tipo de veículo e/ou o nome do subtipo.
              // Então verifica se temos um subtipo de veículo com o
              // mesmo nome antes de prosseguir
              if (VehicleSubtype::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$vehicleSubtypeData['name']}')"
                      )
                    ->where('vehiclesubtypeid', '=', $vehicleSubtypeData['vehicletypeid'])
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do subtipo de {typeName} '{name}'. Já "
                  . "existe um subtipo com o mesmo nome.",
                  [ 'typeName' => $typeName,
                    'name' => $vehicleSubtypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um subtipo de "
                  . "{typeName} com o nome <i>'{name}'</i>.",
                  [ 'typeName' => $typeName,
                    'name' => $vehicleSubtypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de veículo
              $vehicleSubtype->fill($vehicleSubtypeData);
              $vehicleSubtype->save();
              
              // Registra o sucesso
              $this->info("Modificado o subtipo de {typeName} '{name}' "
                . "com sucesso.",
                [ 'typeName'   => $typeName,
                  'name'  => $vehicleSubtypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O subtipo de {typeName} "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'typeName'   => $typeName,
                  'name'  => $vehicleSubtypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Vehicles\Subtypes' ]
              );
              
              // Redireciona para a página de gerenciamento de subtipos
              // de veículos
              return $this->redirect($response,
                'ADM\Parameterization\Vehicles\Subtypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "subtipo de {typeName} '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'typeName' => $typeName,
                'name' => $vehicleSubtypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do subtipo de {typeName}. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "subtipo de {typeName} '{name}'. Erro interno: {error}.",
              [ 'typeName' => $typeName,
                'name' => $vehicleSubtypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do subtipo de {typeName}. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($vehicleSubtype->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o subtipo de veículo "
        . "código {vehicleSubtypeID}.",
        [ 'vehicleSubtypeID' => $vehicleSubtypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este subtipo "
        . "de veículo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Vehicles\Subtypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de veículos
      return $this->redirect($response,
        'ADM\Parameterization\Vehicles\Subtypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Subtipos',
      $this->path('ADM\Parameterization\Vehicles\Subtypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Vehicles\Subtypes\Edit', [
        'vehicleSubtypeID' => $vehicleSubtypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do subtipo de {typeName} '{name}'.",
      [ 'typeName' => $vehicleTypeName,
        'name' => $vehicleSubtype->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/subtypes/vehiclesubtype.twig',
      [ 'formMethod' => 'PUT',
        'vehicleTypes' => $vehicleTypes ])
    ;
  }
  
  /**
   * Remove o subtipo de um tipo de veículo.
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
    $this->debug("Processando à remoção de subtipo de um tipo de "
      . "veículo."
    );
    
    // Recupera o ID
    $vehicleSubtypeID = $args['vehicleSubtypeID'];

    try
    {
      // Recupera as informações do tipo de veículo
      $vehicleSubtype = VehicleSubtype::findOrFail($vehicleSubtypeID);
      
      // Agora apaga o tipo de veículo
      $vehicleSubtype->delete();
      
      // Registra o sucesso
      $this->info("O subtipo de veículo '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $vehicleSubtype->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o subtipo de veículo "
              . "{$vehicleSubtype->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o subtipo de veículo "
        . "código {vehicleSubtypeID} para remoção.",
        [ 'vehicleSubtypeID' => $vehicleSubtypeID ]
      );
      
      $message = "Não foi possível localizar o subtipo de veículo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do subtipo "
        . "de veículo ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $vehicleSubtypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o subtipo de veículo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do subtipo "
        . "de veículo ID {id}. Erro interno: {error}.",
        [ 'id' => $vehicleSubtypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o subtipo de veículo. Erro "
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
}
