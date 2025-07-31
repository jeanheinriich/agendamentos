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
 * O controlador do gerenciamento de ações do sistema que disparam o
 * envio de uma mensagem, seja por e-mail ou SMS.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization;

use App\Models\SystemAction;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class SystemActionsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de ações do sistema que
   * disparam o envio de mensagens.
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
    $this->breadcrumb->push('Ações do sistema',
      $this->path('ADM\Parameterization\SystemActions')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de ações do sistema "
      . "que disparam o envio de mensagens."
    );
    
    // Recupera os dados da sessão
    $systemAction = $this->session->get('systemaction',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/systemactions/systemactions.twig',
      [ 'systemaction' => $systemAction ]
    );
  }

  /**
   * Recupera a relação das ações do sistema em formato JSON.
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
    $this->debug("Acesso à relação de ações do sistema que disparam o "
      . "envio de mensagens."
    );

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
    $this->session->set('systemaction',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Inicializa a query
      $SystemActionQry = SystemAction::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $SystemActionQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $systemActions = $SystemActionQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'systemactionid AS id',
            'name',
            'action',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($systemActions) > 0) {
        $rowCount = $systemActions[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $systemActions
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos ações do sistema cadastradas.";
        } else {
          $error = "Não temos ações do sistema cadastradas cujo "
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
        [ 'module' => 'ações do sistema',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de ações do "
        . "sistema. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'ações do sistema',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de ações do "
        . "sistema. Erro interno."
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
   * Exibe um formulário para adição de uma ação do sistema, quando
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
      $this->debug("Processando à adição de uma ação do sistema que "
        . "dispara o envio de uma mensagem."
      );
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Nome da ação'),
        'action' => V::notBlank()
          ->length(2, 30)
          ->setName('Ação interna no sistema'),
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da ação do sistema
          $systemActionData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma ação com o mesmo nome
          if (SystemAction::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$systemActionData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de propriedade
            $systemAction = new SystemAction();
            $systemAction->fill($systemActionData);
            $systemAction->save();
            
            // Registra o sucesso
            $this->info("Cadastrada a ação do sistema '{name}' com "
              . "sucesso.",
              [ 'name'  => $systemActionData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A ação do sistema <i>'{name}'</i> "
              . "foi cadastrada com sucesso.",
              [ 'name'  => $systemActionData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\SystemActions' ]
            );
            
            // Redireciona para a página de gerenciamento de ações do
            // sistema
            return $this->redirect($response,
              'ADM\Parameterization\SystemActions')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "ação do sistema '{name}'. Já existe uma ação com o "
              . "mesmo nome.",
              [ 'name'  => $systemActionData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma ação do sistema "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $systemActionData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "ação do sistema '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $systemActionData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da ação do sistema. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "ação do sistema '{name}'. Erro interno: {error}.",
            [ 'name'  => $systemActionData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da ação do sistema. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de uma ação do sistema que
    // dispara o envio de uma mensagem
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Ações do sistema',
      $this->path('ADM\Parameterization\SystemActions')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\SystemActions\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de uma ação do sistema que dispara o "
      . "envio de uma mensagem."
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/systemactions/systemaction.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma ação do sistema, quando
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
      $systemActionID = $args['systemActionID'];
      $systemAction = SystemAction::findOrFail($systemActionID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da ação do sistema "
          . "'{name}'.",
          [ 'name' => $systemAction['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'systemactionid' => V::notBlank()
            ->intVal()
            ->setName('ID da ação do sistema'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Nome da ação'),
          'action' => V::notBlank()
            ->length(2, 30)
            ->setName('Ação interna no sistema'),
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da ação do sistema
            $systemActionData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome da ação do
            // sistema
            $save = false;
            if ($systemAction->name != $systemActionData['name']) {
              // Modificamos o nome da ação do sistema, então verifica
              // se temos uma ação com o mesmo nome antes de prosseguir
              if (SystemAction::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$systemActionData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da ação do sistema '{name}'. Já "
                  . "existe uma ação com o mesmo nome.",
                  [ 'name'  => $systemActionData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma ação do "
                  . "sistema com o nome <i>'{name}'</i>.",
                  [ 'name'  => $systemActionData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da ação do sistema
              $systemAction->fill($systemActionData);
              $systemAction->save();
              
              // Registra o sucesso
              $this->info("Modificada a ação do sistema '{name}' com "
                . "sucesso.",
                [ 'name'  => $systemActionData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A ação do sistema <i>'{name}'"
                . "</i> foi modificada com sucesso.",
                [ 'name'  => $systemActionData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\SystemActions' ]
              );
              
              // Redireciona para a página de gerenciamento de ações do
              // sistema
              return $this->redirect($response,
                'ADM\Parameterization\SystemActions')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da ação do sistema '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $systemActionData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da ação do sistema. Erro interno no banco "
              . "de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da ação do sistema '{name}'. Erro interno: {error}.",
              [ 'name'  => $systemActionData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da ação do sistema. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($systemAction->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a ação do sistema com o "
        .  "código {systemActionID}.",
        [ 'systemActionID' => $systemActionID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta ação do "
        . "sistema."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\SystemActions' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de
      // propriedades
      return $this->redirect($response,
        'ADM\Parameterization\SystemActions')
      ;
    }
    
    // Exibe um formulário para edição de uma ação do sistema
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Ações do sistema',
      $this->path('ADM\Parameterization\SystemActions')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\SystemActions\Edit', [
        'systemActionID' => $systemActionID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da ação do sistema '{name}'.",
      [ 'name' => $systemAction['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/systemactions/systemaction.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove a ação do sistema.
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
    $this->debug("Processando à remoção de uma ação do sistema.");
    
    // Recupera o ID
    $systemActionID = $args['systemActionID'];

    try
    {
      // Recupera as informações da ação do sistema
      $systemAction = SystemAction::findOrFail($systemActionID);
      
      // Agora apaga a ação do sistema
      $systemAction->delete();
      
      // Registra o sucesso
      $this->info("A ação do sistema '{name}' foi removida com "
        . "sucesso.",
        [ 'name' => $systemAction->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a ação do sistema "
              . "{$systemAction->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a ação do sistema com o "
        . "código {systemActionID} para remoção.",
        [ 'systemActionID' => $systemActionID ]
      );
      
      $message = "Não foi possível localizar a ação do sistema para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da ação do "
        . "sistema ID {id}. Erro interno no banco de dados: {error}.",
        [ 'id' => $systemActionID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a ação do sistema. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da ação do "
        . "sistema ID {id}. Erro interno: {error}.",
        [ 'id' => $systemActionID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a ação do sistema. Erro "
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
