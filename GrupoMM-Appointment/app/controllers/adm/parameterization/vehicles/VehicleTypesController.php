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
 * O controlador do gerenciamento de tipos de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Vehicles;

use App\Models\VehicleType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class VehicleTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de veículos.
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
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Vehicles\Types')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de veículos.");
    
    // Recupera os dados da sessão
    $vehicleType = $this->session->get('vehicletype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/types/vehicletypes.twig',
      [ 'vehicletype' => $vehicleType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de veículos em formato JSON.
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
    $this->debug("Acesso à relação de tipos de veículos.");
    
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
    $name = $postParams['searchValue'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicletype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleTypeQry = VehicleType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $VehicleTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $vehicleTypes = $VehicleTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehicletypeid AS id',
            'name',
            'singular',
            'fipename',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicleTypes) > 0) {
        $rowCount = $vehicleTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicleTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de veículos cadastrados.";
        } else {
          $error = "Não temos tipos de veículos cadastrados cujo "
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
        [ 'module' => 'tipos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "veículos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
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
   * Exibe um formulário para adição de um tipo de veículo, quando
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
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de veículo.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de veículo'),
        'singular' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de veículo no singular'),
        'fipename' => V::optional(
              V::alpha()
            )->setName('Nome no sistema Fipe')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de veículo
          $vehicleTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de veículo com o
          // mesmo nome
          if (VehicleType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$vehicleTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de veículo
            $vehicleType = new VehicleType();
            $vehicleType->fill($vehicleTypeData);
            $vehicleType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de veículo '{name}' com "
              . "sucesso.",
              [ 'name'  => $vehicleTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de veículo <i>'{name}'</i> "
              . "foi cadastrado com sucesso.",
              [ 'name'  => $vehicleTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Vehicles\Types' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // veículos
            return $this->redirect($response,
              'ADM\Parameterization\Vehicles\Types'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de veículo '{name}'. Já existe um tipo de "
              . "veículo com o mesmo nome.",
              [ 'name'  => $vehicleTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de veículo "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $vehicleTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de veículo '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $vehicleTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de veículo. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de veículo '{name}'. Erro interno: {error}.",
            [ 'name'  => $vehicleTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de veículo. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Vehicles\Types')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Vehicles\Types\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de veículo.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/types/vehicletype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de veículo, quando
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
    try
    {
      // Recupera as informações do tipo de veículo
      $vehicleTypeID = $args['vehicleTypeID'];
      $vehicleType = VehicleType::findOrFail($vehicleTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do tipo de veículo "
          . "'{name}'.",
          [ 'name' => $vehicleType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de veículo'),
          'singular' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de veículo no singular'),
          'fipename' => V::optional(
                V::alpha()
              )->setName('Nome no sistema Fipe')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de veículo
            $vehicleTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // veículo
            $save = false;
            if ($vehicleType->name != $vehicleTypeData['name']) {
              // Modificamos o nome do tipo de veículo, então verifica
              // se temos um tipo de veículo com o mesmo nome antes de
              // prosseguir
              if (VehicleType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$vehicleTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de veículo '{name}'. Já "
                  . "existe um tipo de veículo com o mesmo nome.",
                  [ 'name'  => $vehicleTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de veículo "
                  . "com o nome <i>'{name}'</i>.",
                  [ 'name'  => $vehicleTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de veículo
              $vehicleType->fill($vehicleTypeData);
              $vehicleType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de veículo '{name}' "
                . "com sucesso.",
                [ 'name'  => $vehicleTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de veículo "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $vehicleTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Vehicles\Types' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // veículos
              return $this->redirect($response,
                'ADM\Parameterization\Vehicles\Types')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de veículo '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $vehicleTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de veículo. Erro interno no banco "
              . "de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de veículo '{name}'. Erro interno: {error}.",
              [ 'name'  => $vehicleTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de veículo. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($vehicleType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de veículo "
        . "código {vehicleTypeID}.",
        [ 'vehicleTypeID' => $vehicleTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "veículo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Vehicles\Types' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de veículos
      return $this->redirect($response,
        'ADM\Parameterization\Vehicles\Types')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Vehicles\Types')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Vehicles\Types\Edit', [
        'vehicleTypeID' => $vehicleTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de veículo '{name}'.",
      [ 'name' => $vehicleType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/types/vehicletype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o tipo de veículo.
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
    $this->debug("Processando à remoção de tipo de veículo.");
    
    // Recupera o ID
    $vehicleTypeID = $args['vehicleTypeID'];

    try
    {
      // Recupera as informações do tipo de veículo
      $vehicleType = VehicleType::findOrFail($vehicleTypeID);
      
      // Agora apaga o tipo de veículo
      $vehicleType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de veículo '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $vehicleType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de veículo "
              . "{$vehicleType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de veículo "
        . "código {vehicleTypeID} para remoção.",
        [ 'vehicleTypeID' => $vehicleTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de veículo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de veículo ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $vehicleTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de veículo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de veículo ID {id}. Erro interno: {error}.",
        [ 'id' => $vehicleTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de veículo. Erro "
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
