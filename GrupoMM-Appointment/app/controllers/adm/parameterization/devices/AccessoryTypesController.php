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
 * O controlador do gerenciamento de tipos de acessórios de um
 * equipamento de rastreamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Devices;

use App\Models\AccessoryType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class AccessoryTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de acessórios.
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
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Acessórios', '');
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Devices\Accessories\Types')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de acessórios.");
    
    // Recupera os dados da sessão
    $accessoryType = $this->session->get('accessorytype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/accessories/types/accessorytypes.twig',
      [ 'accessorytype' => $accessoryType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de acessórios em formato JSON.
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
    $this->debug("Acesso à relação de tipos de acessórios.");
    
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
    $this->session->set('accessorytype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $AccessoryTypeQry = AccessoryType::query();
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $AccessoryTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $accessoryTypes = $AccessoryTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'accessorytypeid AS id',
            'name',
            'description',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($accessoryTypes) > 0) {
        $rowCount = $accessoryTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $accessoryTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de acessórios cadastrados.";
        } else {
          $error = "Não temos tipos de acessórios cadastrados cujo "
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
        [ 'module' => 'tipos de acessórios',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "acessórios. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de acessórios',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "acessórios. Erro interno."
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
   * Exibe um formulário para adição de um tipo de acessório, quando
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
      $this->debug("Processando à adição de tipo de acessório.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Tipo de acessório'),
        'description' => V::notBlank()
          ->setName('Descrição da funcionalidade')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de acessório
          $accessoryTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de acessório com o
          // mesmo nome
          if (AccessoryType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$accessoryTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de acessório
            $accessoryType = new AccessoryType();
            $accessoryType->fill($accessoryTypeData);
            $accessoryType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de acessório '{name}' com "
              . "sucesso.",
              [ 'name'  => $accessoryTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de acessório "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $accessoryTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Devices\Accessories\Types' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // acessórios
            return $this->redirect($response,
              'ADM\Parameterization\Devices\Accessories\Types'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de acessório '{name}'. Já existe um tipo de "
              . "acessório com o mesmo nome.",
              [ 'name'  => $accessoryTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de acessório "
              . "com o nome <i>'{name}'</i>.",
              [ 'name'  => $accessoryTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de acessório '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $accessoryTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de acessório. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de acessório '{name}'. Erro interno: {error}.",
            [ 'name'  => $accessoryTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de acessório. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de acessório
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Acessórios', '');
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Devices\Accessories\Types')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Devices\Accessories\Types\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de acessório.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/accessories/types/accessorytype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de acessório, quando
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
      // Recupera as informações do tipo de acessório
      $accessoryTypeID = $args['accessoryTypeID'];
      $accessoryType = AccessoryType::findOrFail($accessoryTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do tipo de acessório "
          . "'{name}'.",
          [ 'name' => $accessoryType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de acessório'),
          'description' => V::notBlank()
            ->setName('Descrição da funcionalidade')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de acessório
            $accessoryTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // acessório
            $save = false;
            if ($accessoryType->name != $accessoryTypeData['name']) {
              // Modificamos o nome do tipo de acessório, então verifica
              // se temos um tipo de acessório com o mesmo nome antes de
              // prosseguir
              if (AccessoryType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$accessoryTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de acessório '{name}'. Já "
                  . "existe um tipo de acessório com o mesmo nome.",
                  [ 'name'  => $accessoryTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "acessório com o nome <i>'{name}'</i>.",
                  [ 'name'  => $accessoryTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de acessório
              $accessoryType->fill($accessoryTypeData);
              $accessoryType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de acessório '{name}' "
                . "com sucesso.",
                [ 'name'  => $accessoryTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de acessório "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $accessoryTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Devices\Accessories\Types' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // acessórios
              return $this->redirect($response,
                'ADM\Parameterization\Devices\Accessories\Types')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de acessório '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'name'  => $accessoryTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de acessório. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de acessório '{name}'. Erro interno: {error}.",
              [ 'name'  => $accessoryTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de acessório. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($accessoryType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de acessório "
        . "código {accessoryTypeID}.",
        [ 'accessoryTypeID' => $accessoryTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "acessório."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Devices\Accessories\Types' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de acessórios
      return $this->redirect($response,
        'ADM\Parameterization\Devices\Accessories\Types')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de acessório
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Acessórios', '');
    $this->breadcrumb->push('Tipos',
      $this->path('ADM\Parameterization\Devices\Accessories\Types')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Devices\Accessories\Types\Edit', [
        'accessoryTypeID' => $accessoryTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de acessório '{name}'.",
      [ 'name' => $accessoryType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/accessories/types/accessorytype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o tipo de acessório.
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
    $this->debug("Processando à remoção do tipo de acessório.");
    
    // Recupera o ID
    $accessoryTypeID = $args['accessoryTypeID'];

    try
    {
      // Recupera as informações do tipo de acessório
      $accessoryType = AccessoryType::findOrFail($accessoryTypeID);
      
      // Agora apaga o tipo de acessório
      $accessoryType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de acessório '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $accessoryType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de acessório "
              . "{$accessoryType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de acessório "
        . "código {accessoryTypeID} para remoção.",
        [ 'accessoryTypeID' => $accessoryTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de acessório para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de acessório ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $accessoryTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de acessório. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de acessório ID {id}. Erro interno: {error}.",
        [ 'id' => $accessoryTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de acessório. Erro "
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
