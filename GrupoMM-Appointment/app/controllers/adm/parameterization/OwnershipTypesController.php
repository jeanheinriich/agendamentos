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
 * O controlador do gerenciamento de tipos de propriedades (posse) de um
 * equipamento ou Sim/Card.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization;

use App\Models\OwnershipType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class OwnershipTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de propriedades.
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
    $this->breadcrumb->push('Tipos de propriedades',
      $this->path('ADM\Parameterization\OwnershipTypes')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de propriedades.");
    
    // Recupera os dados da sessão
    $ownershipType = $this->session->get('ownershiptype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/ownershiptypes/ownershiptypes.twig',
      [ 'ownershiptype' => $ownershipType ]
    );
  }

  /**
   * Recupera a relação dos tipos de propriedades em formato JSON.
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
    $this->debug("Acesso à relação de tipos de propriedades.");

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
    $this->session->set('ownershiptype',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Inicializa a query
      $OwnershipTypeQry = OwnershipType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $OwnershipTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $ownershipTypes = $OwnershipTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'ownershiptypeid AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($ownershipTypes) > 0) {
        $rowCount = $ownershipTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $ownershipTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de propriedades cadastrados.";
        } else {
          $error = "Não temos tipos de propriedades cadastrados cujo "
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
        [ 'module' => 'tipos de propriedades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "propriedades. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de propriedades',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "propriedades. Erro interno."
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
   * Exibe um formulário para adição de um tipo de propriedade, quando
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
      $this->debug("Processando à adição de tipo de propriedade.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 20)
          ->setName('Tipo de propriedade')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de propriedade
          $ownershipTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de propriedade com o
          // mesmo nome
          if (OwnershipType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$ownershipTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de propriedade
            $ownershipType = new OwnershipType();
            $ownershipType->fill($ownershipTypeData);
            $ownershipType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de propriedade '{name}' "
              . "com sucesso.",
              [ 'name'  => $ownershipTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de propriedade "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $ownershipTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\OwnershipTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // propriedades
            return $this->redirect($response,
              'ADM\Parameterization\OwnershipTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de propriedade '{name}'. Já existe um tipo de "
              . "propriedade com o mesmo nome.",
              [ 'name'  => $ownershipTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de propriedade "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $ownershipTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de propriedade '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $ownershipTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de propriedade. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de propriedade '{name}'. Erro interno: {error}.",
            [ 'name'  => $ownershipTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de propriedade. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de propriedade
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Tipos de propriedades',
      $this->path('ADM\Parameterization\OwnershipTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\OwnershipTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de propriedade.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/ownershiptypes/ownershiptype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de propriedade, quando
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
      // Recupera as informações do tipo de propriedade
      $ownershipTypeID = $args['ownershipTypeID'];
      $ownershipType = OwnershipType::findOrFail($ownershipTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do tipo de propriedade "
          . "'{name}'.",
          [ 'name' => $ownershipType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 20)
            ->setName('Tipo de propriedade')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de propriedade
            $ownershipTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // propriedade
            $save = false;
            if ($ownershipType->name != $ownershipTypeData['name']) {
              // Modificamos o nome do tipo de propriedade, então
              // verifica se temos um tipo de propriedade com o mesmo
              // nome antes de prosseguir
              if (OwnershipType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$ownershipTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de propriedade '{name}'. Já "
                  . "existe um tipo de propriedade com o mesmo nome.",
                  [ 'name'  => $ownershipTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "propriedade com o nome <i>'{name}'</i>.",
                  [ 'name'  => $ownershipTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de propriedade
              $ownershipType->fill($ownershipTypeData);
              $ownershipType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de propriedade '{name}' "
                . "com sucesso.",
                [ 'name'  => $ownershipTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de propriedade "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $ownershipTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\OwnershipTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // propriedades
              return $this->redirect($response,
                'ADM\Parameterization\OwnershipTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de propriedade '{name}'. Erro interno no "
              . "banco de dados: {error}.",
              [ 'name'  => $ownershipTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de propriedade. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de propriedade '{name}'. Erro interno: "
              . "{error}.",
              [ 'name'  => $ownershipTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de propriedade. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($ownershipType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de propriedade "
        .  "código {ownershipTypeID}.",
        [ 'ownershipTypeID' => $ownershipTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "propriedade."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\OwnershipTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de
      // propriedades
      return $this->redirect($response,
        'ADM\Parameterization\OwnershipTypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de propriedade
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Tipos de propriedades',
      $this->path('ADM\Parameterization\OwnershipTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\OwnershipTypes\Edit', [
        'ownershipTypeID' => $ownershipTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de propriedade '{name}'.",
      [ 'name' => $ownershipType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/ownershiptypes/ownershiptype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o tipo de propriedade.
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
    $this->debug("Processando à remoção de tipo de propriedade.");
    
    // Recupera o ID
    $ownershipTypeID = $args['ownershipTypeID'];

    try
    {
      // Recupera as informações do tipo de propriedade
      $ownershipType = OwnershipType::findOrFail($ownershipTypeID);
      
      // Agora apaga o tipo de propriedade
      $ownershipType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de propriedade '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $ownershipType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de propriedade {$ownershipType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de propriedade "
        . "código {ownershipTypeID} para remoção.",
        [ 'ownershipTypeID' => $ownershipTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de propriedade "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de propriedade ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $ownershipTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de propriedade. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de propriedade ID {id}. Erro interno: {error}.",
        [ 'id' => $ownershipTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de propriedade. Erro "
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
