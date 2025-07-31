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
 * O controlador do gerenciamento de permissões.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization;

use App\Models\Group;
use App\Models\Permission;
use App\Models\PermissionPerGroup;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de permissões.
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
    $this->breadcrumb->push('Permissões',
      $this->path('ADM\Parameterization\Permissions')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de permissões.");
    
    // Recupera os dados da sessão
    $permission = $this->session->get('permission',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/permissions/permissions.twig',
      [ 'permission' => $permission ])
    ;
  }

  /**
   * Recupera a relação das permissões em formato JSON.
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
    $this->debug("Acesso à relação de permissões.");

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
    $this->session->set('permission',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Realiza a consulta
      $sql = "SELECT permission.id,
                     permission.name,
                     permission.description,
                     permission.g1,
                     permission.g2,
                     permission.g3,
                     permission.g4,
                     permission.g5,
                     permission.g6,
                     count(*) OVER() AS fullcount
                FROM erp.getPermissionData('%{$name}%') AS permission
               ORDER BY $ORDER
               LIMIT $length
              OFFSET $start;"
      ;
      $permissions = $this->DB->select($sql);
      
      if (count($permissions) > 0) {
        $rowCount = $permissions[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $permissions
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos permissões cadastradas.";
        } else {
          $error = "Não temos permissões cadastradas cujo nome "
            . "contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'permissões',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "permissões. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'permissões',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "permissões. Erro interno."
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
   * Exibe um formulário para adição de uma permissão, quando
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
    // Recupera as informações de grupos de usuários
    $groups = Group::orderBy('groupid')
      ->get([
          'groupid AS id',
          'name'
        ])
    ;
    
    // Define os métodos HTTP usados
    $httpMethods = [ 'GET', 'PATCH', 'POST', 'PUT', 'DELETE' ];
    
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de permissão.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 100)
          ->setName('Nome da permissão'),
        'description' => V::notBlank()
          ->length(2, 100)
          ->setName('Descrição da permissão'),
        'permissions' => V::arrayVal()
          ->setName('Permissões por grupo')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da permissão
          $permissionData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma permissão com o mesmo
          // nome
          if (Permission::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$permissionData['name']}')"
                  )
                ->count() === 0) {
            // Precisa retirar dos parâmetros as informações
            // correspondentes aos dados das permissões por grupo
            $newPermissions = $permissionData['permissions'];
            unset($permissionData['permissions']);
            
            // Grava as informações da permissão
            
            // Iniciamos a transação
            $this->DB->beginTransaction();
            
            // Grava a nova permissão
            $permission = new Permission();
            $permission->fill($permissionData);
            $permission->save();
            $permissionID = $permission->permissionid;
            
            // Analisa as permissões por grupo, fazendo as devidas
            // inserções
            foreach($newPermissions AS $groupID => $data) {
              foreach($data AS $httpMethod => $value) {
                if ($value === "true") {
                  // Adiciona a permissão
                  $permissionPerGroup = new PermissionPerGroup();
                  $permissionPerGroup->permissionid = $permissionID;
                  $permissionPerGroup->groupid = $groupID;
                  $permissionPerGroup->httpmethod = $httpMethod;
                  $permissionPerGroup->save();
                }
              }
            }
            
            // Efetiva a transação
            $this->DB->commit();
            
            // Registra o sucesso
            $this->info("Cadastrada a permissão '{name}' com sucesso.",
              [ 'name'  => $permissionData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A permissão <i>'{name}'</i> foi "
              . "cadastrada com sucesso.",
              [ 'name'  => $permissionData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Permissions' ]
            );
            
            // Redireciona para a página de gerenciamento de permissões
            return $this->redirect($response,
              'ADM\Parameterization\Permissions')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "permissão '{name}'. Já existe uma permissão com o "
              . "mesmo nome.",
              [ 'name'  => $permissionData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma permissão com o "
              . "nome <i>'{name}'</i>.",
              [ 'name'  => $permissionData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();
          
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "permissão '{name}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $permissionData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da permissão. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();
          
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "permissão '{name}'. Erro interno: {error}.",
            [ 'name'  => $permissionData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da permissão. Erro interno."
          );
        }
      }
    } else {
      // Gera um conjunto de valores novos
      
      // Define os valores padrões
      $defaultValues = [ ];
      for ($group = 1; $group <= 6; $group++) {
        $defaultValues[$group]['GET'] = "false";
        $defaultValues[$group]['POST'] = "false";
        $defaultValues[$group]['PUT'] = "false";
        $defaultValues[$group]['DELETE'] = "false";
      }

      $permission = [ ];
      $permission['name'] = '';
      $permission['description'] = '';
      $permission['permissions'] = $defaultValues;
      
      $this->validator->setValues($permission);
    }
    
    // Exibe um formulário para adição de uma permissão
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Permissões',
      $this->path('ADM\Parameterization\Permissions')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Permissions\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de permissão.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/permissions/permission.twig',
      [ 'formMethod' => 'POST',
        'groups' => $groups,
        'httpMethods' => $httpMethods ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma permissão, quando
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
      // Recupera as informações da permissão
      $permissionID = $args['permissionID'];
      $permission = Permission::findOrFail($permissionID);
      $permissionpergroup = Permission::findOrFail($permissionID)
        ->permissionPerGroup()
        ->get()
      ;
      
      // Recupera as informações de grupos de usuários
      $groups = Group::orderBy('groupid')
        ->get([
            'groupid AS id',
            'name'
          ])
      ;
      
      // Define os métodos HTTP usados
      $httpMethods = [ 'GET', 'PATCH', 'POST', 'PUT', 'DELETE' ];
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da permissão '{name}'.",
          [ 'name' => $permission['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 100)
            ->setName('Nome da permissão'),
          'description' => V::notBlank()
            ->length(2, 100)
            ->setName('Descrição da permissão'),
          'permissions' => V::arrayVal()
            ->setName('Permissões por grupo')
        ]);
        
        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da permissão
            $permissionData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome da permissão
            $save = false;
            if ($permission->name != $permissionData['name']) {
              // Modificamos o nome da permissão, então verifica se temos
              // uma permissão com o mesmo nome antes de prosseguir
              if (Permission::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$permissionData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da permissão '{name}'. Já existe uma "
                  . "permissão com o mesmo nome.",
                  [ 'name'  => $permissionData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma permissão com "
                  . "o nome <i>'{name}'</i>.",
                  [ 'name'  => $permissionData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Precisa retirar dos parâmetros as informações
              // correspondentes aos dados das permissões por grupo
              $newPermissions = $permissionData['permissions'];
              unset($permissionData['permissions']);
              
              // Define os valores padrões para as permissões correntes
              for ($group = 1; $group <= 6; $group++) {
                $oldPermissions[$group]['GET'] = "false";
                $oldPermissions[$group]['PATCH'] = "false";
                $oldPermissions[$group]['POST'] = "false";
                $oldPermissions[$group]['PUT'] = "false";
                $oldPermissions[$group]['DELETE'] = "false";
              }
              
              // Acrescenta as permissões por grupo definidas para
              // permitir a análise do que precisa ser mantido ou
              // alterado
              foreach($permissionpergroup->toArray() AS $grouppermission) {
                $oldPermissions[
                  $grouppermission['groupid']
                ][$grouppermission['httpmethod']] = "true";
              }
              
              // Inicia a transação
              $this->DB->beginTransaction();
              
              // Realiza as modificações necessárias
              
              // Grava as informações da permissão
              $permission->fill($permissionData);
              $permission->save();
              
              // Analisa as permissões por grupo, fazendo as devidas
              // modificações
              foreach($oldPermissions AS $groupID => $data) {
                foreach($data AS $httpMethod => $value) {
                  if ($oldPermissions[$groupID][$httpMethod] !== $newPermissions[$groupID][$httpMethod]) {
                    if ($oldPermissions[$groupID][$httpMethod] === "true") {
                      // Remove a permissão
                      PermissionPerGroup::where('permissionid',
                            $permissionID
                          )
                        ->where('groupid', $groupID)
                        ->where('httpmethod', $httpMethod)
                        ->delete()
                      ;
                    } else {
                      // Adiciona a permissão
                      $permissionPerGroup = new PermissionPerGroup();
                      $permissionPerGroup->permissionid = $permissionID;
                      $permissionPerGroup->groupid = $groupID;
                      $permissionPerGroup->httpmethod = $httpMethod;
                      $permissionPerGroup->save();
                    }
                  }
                }
              }
              
              // Efetiva a transação
              $this->DB->commit();
              
              // Registra o sucesso
              $this->info("Modificada a permissão '{name}' com "
                . "sucesso.",
                [ 'name'  => $permissionData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A permissão <i>'{name}'</i> "
                . "foi modificada com sucesso.",
                [ 'name'  => $permissionData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Permissions' ]
              );
              
              // Redireciona para a página de gerenciamento de
              // permissões
              return $this->redirect($response,
                'ADM\Parameterization\Permissions')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da permissão '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $permissionData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da permissão. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da permissão '{name}'. Erro interno: {error}.",
              [ 'name'  => $permissionData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da permissão. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        
        // Recupera as informações da permissão
        $permissionData = $permission->toArray();
        
        // Define os valores padrões para as permissões por grupo
        for ($group = 1; $group <= 6; $group++) {
          $permissions[$group]['GET'] = "false";
          $permissions[$group]['POST'] = "false";
          $permissions[$group]['PUT'] = "false";
          $permissions[$group]['DELETE'] = "false";
        }
        
        // Converte as permissões por grupo para permitir a renderização
        $permissions = [ ];
        foreach($permissionpergroup->toArray() AS $grouppermission) {
          $permissions[
            $grouppermission['groupid']
          ][$grouppermission['httpmethod']] = "true";
        }
        
        // Acrescenta as permissões por grupo aos dados a serem
        // renderizados
        $permissionData['permissions'] = $permissions;
        
        $this->validator->setValues($permissionData);
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a permissão código "
        . "{permissionID}.",
        [ 'permissionID' => $permissionID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta "
        . "permissão."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Permissions' ]
      );
      
      // Redireciona para a página de gerenciamento de permissões
      return $this->redirect($response,
        'ADM\Parameterization\Permissions'
      );
    }
    
    // Exibe um formulário para edição de uma permissão
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Permissões',
      $this->path('ADM\Parameterization\Permissions')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Permissions\Edit', [
        'permissionID' => $permissionID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da permissão '{name}'.",
      [ 'name' => $permission['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/permissions/permission.twig',
      [ 'formMethod' => 'PUT',
        'groups' => $groups,
        'httpMethods' => $httpMethods ])
    ;
  }
  
  /**
   * Remove a permissão.
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
    $this->debug("Processando à remoção de permissão.");
    
    // Recupera o ID
    $permissionID = $args['permissionID'];

    try
    {
      // Recupera as informações da permissão
      $permission = Permission::findOrFail($permissionID);
      
      // Inicia a transação
      $this->DB->beginTransaction();
      
      // Apaga a permissão e suas permissões por grupo
      $permission->deleteCascade();
      
      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("A permissão '{name}' foi removida com sucesso.",
        [ 'name' => $permission->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a permissão {$permission->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a permissão código "
        . "{permissionID} para remoção.",
        [ 'permissionID' => $permissionID ]
      );
      
      $message = "Não foi possível localizar a permissão para remoção.";
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "permissão ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $permissionID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a permissão. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "permissão ID {id}. Erro interno: {error}.",
        [ 'id' => $permissionID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a permissão. Erro interno.";
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
