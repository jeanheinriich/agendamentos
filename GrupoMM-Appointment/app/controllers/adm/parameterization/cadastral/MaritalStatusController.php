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
 * O controlador do gerenciamento de estados civis.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\MaritalStatus;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MaritalStatusController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de estados civis.
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
    $this->breadcrumb->push('Estados civis',
      $this->path('ADM\Parameterization\Cadastral\MaritalStatus')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de estados civis.");
    
    // Recupera os dados da sessão
    $maritalStatus = $this->session->get('maritalstatus',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/maritalstatus/civilstates.twig',
      [ 'maritalstatus' => $maritalStatus ])
    ;
  }
  
  /**
   * Recupera a relação dos estados civis em formato JSON.
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
    $this->debug("Acesso à relação de estados civis.");
    
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
    $this->session->set('maritalstatus',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $MaritalStatusQry = MaritalStatus::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $MaritalStatusQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $maritalStatus = $MaritalStatusQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'maritalstatusid AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($maritalStatus) > 0) {
        $rowCount = $maritalStatus[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $maritalStatus
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos estados civis cadastrados.";
        } else {
          $error = "Não temos estados civis cadastrados cujo nome "
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
        [ 'module' => 'estados civis',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de estados "
        . "civis. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'estados civis',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de estados "
        . "civis. Erro interno."
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
   * Exibe um formulário para adição de um estado civil, quando
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
      $this->debug("Processando à adição de estado civil.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Estado civil')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do estado civil
          $maritalStatusData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um estado civil com o
          // mesmo nome
          if (MaritalStatus::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$maritalStatusData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo estado civil
            $maritalStatus = new MaritalStatus();
            $maritalStatus->fill($maritalStatusData);
            $maritalStatus->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o estado civil '{name}' com "
              . "sucesso.",
              [ 'name'  => $maritalStatusData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O estado civil <i>'{name}'</i> "
              . "foi cadastrado com sucesso.",
              [ 'name'  => $maritalStatusData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' =>
                  'ADM\Parameterization\Cadastral\MaritalStatus' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // maritalos
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\MaritalStatus'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "estado civil '{name}'. Já existe um estado civil com "
              . "o mesmo nome.",
              [ 'name'  => $maritalStatusData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um estado civil com o "
              . "nome <i>'{name}'</i>.",
              [ 'name'  => $maritalStatusData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "estado civil '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $maritalStatusData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do estado civil. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "estado civil '{name}'. Erro interno: {error}.",
            [ 'name'  => $maritalStatusData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do estado civil. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um estado civil
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Estados civis',
      $this->path('ADM\Parameterization\Cadastral\MaritalStatus')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\MaritalStatus\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de estado civil.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/maritalstatus/maritalstatus.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um estado civil, quando
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
      // Recupera as informações do estado civil
      $maritalStatusID = $args['maritalStatusID'];
      $maritalStatus = MaritalStatus::findOrFail($maritalStatusID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do estado civil "
          . "'{name}'.",
          [ 'name' => $maritalStatus['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Estado civil')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do estado civil
            $maritalStatusData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // maritalo
            $save = false;
            if ($maritalStatus->name != $maritalStatusData['name']) {
              // Modificamos o nome do estado civil, então verifica
              // se temos um estado civil com o mesmo nome antes de
              // prosseguir
              if (MaritalStatus::whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$maritalStatusData['name']}')"
                    )
                   ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do estado civil '{name}'. Já existe "
                  . "um estado civil com o mesmo nome.",
                  [ 'name'  => $maritalStatusData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um estado civil "
                  . "com o nome <i>'{name}'</i>.",
                  [ 'name'  => $maritalStatusData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do estado civil
              $maritalStatus->fill($maritalStatusData);
              $maritalStatus->save();
              
              // Registra o sucesso
              $this->info("Modificado o estado civil '{name}' com "
                . "sucesso.",
                [ 'name'  => $maritalStatusData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O estado civil <i>'{name}'</i> "
                . "foi modificado com sucesso.",
                [ 'name'  => $maritalStatusData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\MaritalStatus' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // maritalos
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\MaritalStatus'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "estado civil '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $maritalStatusData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do estado civil. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "estado civil '{name}'. Erro interno: {error}.",
              [ 'name'  => $maritalStatusData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do estado civil. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($maritalStatus->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o estado civil código "
        . "{maritalStatusID}.",
        [ 'maritalStatusID' => $maritalStatusID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este estado "
        . "civil."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\MaritalStatus' ]
      );
      
      // Redireciona para a página de gerenciamento de estados civis
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\MaritalStatus'
      );
    }
    
    // Exibe um formulário para edição de um estado civil
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Estados civis',
      $this->path('ADM\Parameterization\Cadastral\MaritalStatus')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\MaritalStatus\Edit',
        [ 'maritalStatusID' => $maritalStatusID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do estado civil '{name}'.",
      [ 'name' => $maritalStatus['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/maritalstatus/maritalstatus.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }
  
  /**
   * Remove o estado civil.
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
    $this->debug("Processando à remoção de estado civil.");
    
    // Recupera o ID
    $maritalStatusID = $args['maritalStatusID'];

    try
    {
      // Recupera as informações do estado civil
      $maritalStatus = MaritalStatus::findOrFail($maritalStatusID);
      
      // Agora apaga o estado civil
      $maritalStatus->delete();
      
      // Registra o sucesso
      $this->info("O estado civil '{name}' foi removido com sucesso.",
        [ 'name' => $maritalStatus->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o estado civil "
              . "{$maritalStatus->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o estado civil código "
        . "{maritalStatusID} para remoção.",
        [ 'maritalStatusID' => $maritalStatusID ]
      );
      
      $message = "Não foi possível localizar o estado civil para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do estado "
        . "civil ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $maritalStatusID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o estado civil. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do estado "
        . "civil ID {id}. Erro interno: {error}.",
        [ 'id' => $maritalStatusID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o estado civil. Erro "
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
