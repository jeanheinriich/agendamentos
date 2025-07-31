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
 * O controlador do gerenciamento de modelos de SIM Card.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Telephony;

use App\Models\SimCardType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class SimCardTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de modelos de SIM Card.
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
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Modelos de SIM Card',
      $this->path('ADM\Parameterization\Telephony\SimCardTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de modelos de SIM Card.");
    
    // Recupera os dados da sessão
    $simcardType = $this->session->get('simcardtype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/simcardtypes/simcardtypes.twig',
      [ 'simcardtype' => $simcardType ])
    ;
  }
  
  /**
   * Recupera a relação dos modelos de SIM Card em formato JSON.
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
    $this->debug("Acesso à relação de modelos de SIM Card.");
    
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
    $this->session->set('simcardtype',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $SimCardTypeQry = SimCardType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $SimCardTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $simcardTypes = $SimCardTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'simcardtypeid AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($simcardTypes) > 0) {
        $rowCount = $simcardTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $simcardTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos modelos de SIM Card cadastrados.";
        } else {
          $error = "Não temos modelos de SIM Card cadastrados cujo "
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
        [ 'module' => 'modelos de SIM Card',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de SIM Card. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'modelos de SIM Card',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de SIM Card. Erro interno."
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
   * Exibe um formulário para adição de um modelo de SIM Card, quando
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
      $this->debug("Processando à adição de modelo de SIM Card.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 20)
          ->setName('Modelo de SIM Card')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do modelo de SIM Card
          $simcardTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um modelo de SIM Card com o
          // mesmo nome
          if (SimCardType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$simcardTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo modelo de SIM Card
            $simcardType = new SimcardType();
            $simcardType->fill($simcardTypeData);
            $simcardType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o modelo de SIM Card '{name}' "
              . "com sucesso.",
              [ 'name'  => $simcardTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O modelo de SIM Card "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $simcardTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Telephony\SimCardTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de modelos de
            // SIM Card
            return $this->redirect($response,
              'ADM\Parameterization\Telephony\SimCardTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "modelo de SIM Card '{name}'. Já existe um modelo de "
              . "SIM Card com o mesmo nome.",
              [ 'name'  => $simcardTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um modelo de SIM Card "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $simcardTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de SIM Card '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $simcardTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de SIM Card. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de SIM Card '{name}'. Erro interno: {error}.",
            [ 'name'  => $simcardTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de SIM Card. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um modelo de SIM Card
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Modelos de SIM Card',
      $this->path('ADM\Parameterization\Telephony\SimCardTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Telephony\SimCardTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de modelo de SIM Card.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/simcardtypes/simcardtype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um modelo de SIM Card, quando
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
      // Recupera as informações do modelo de SIM Card
      $simcardTypeID = $args['simcardTypeID'];
      $simcardType = SimCardType::findOrFail($simcardTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do modelo de SIM Card "
          . "'{name}'.",
          [ 'name' => $simcardType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 20)
            ->setName('Modelo de SIM Card')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do modelo de SIM Card
            $simcardTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do modelo de
            // SIM Card
            $save = false;
            if ($simcardType->name != $simcardTypeData['name']) {
              // Modificamos o nome do modelo de SIM Card, então verifica
              // se temos um modelo de SIM Card com o mesmo nome antes de
              // prosseguir
              if (SimCardType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$simcardTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do modelo de SIM Card '{name}'. Já "
                  . "existe um modelo de SIM Card com o mesmo nome.",
                  [ 'name'  => $simcardTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um modelo de Sim "
                  . "Card com o nome <i>'{name}'</i>.",
                  [ 'name'  => $simcardTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do modelo de SIM Card
              $simcardType->fill($simcardTypeData);
              $simcardType->save();
              
              // Registra o sucesso
              $this->info("Modificado o modelo de SIM Card '{name}' "
                . "com sucesso.",
                [ 'name'  => $simcardTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O modelo de SIM Card "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $simcardTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Telephony\SimCardTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de modelos
              // de SIM Card
              return $this->redirect($response,
                'ADM\Parameterization\Telephony\SimCardTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do modelo de SIM Card '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'name'  => $simcardTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do modelo de SIM Card. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do modelo de SIM Card '{name}'. Erro interno: "
              . "{error}.",
              [ 'name'  => $simcardTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do modelo de SIM Card. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($simcardType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de SIM Card "
        . "código {simcardTypeID}.",
        [ 'simcardTypeID' => $simcardTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este modelo de "
        . "SIM Card."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Telephony\SimCardTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de modelos de SIM Card
      return $this->redirect($response,
        'ADM\Parameterization\Telephony\SimCardTypes')
      ;
    }
    
    // Exibe um formulário para edição de um modelo de SIM Card
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Modelos de SIM Card',
      $this->path('ADM\Parameterization\Telephony\SimCardTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Telephony\SimCardTypes\Edit', [
        'simcardTypeID' => $simcardTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do modelo de SIM Card '{name}'.",
      [ 'name' => $simcardType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/simcardtypes/simcardtype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o modelo de SIM Card.
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
    $this->debug("Processando à remoção de modelo de SIM Card.");
    
    // Recupera o ID
    $simcardTypeID = $args['simcardTypeID'];

    try
    {
      // Recupera as informações do modelo de SIM Card
      $simcardType = SimCardType::findOrFail($simcardTypeID);
      
      // Agora apaga o modelo de SIM Card
      $simcardType->delete();
      
      // Registra o sucesso
      $this->info("O modelo de SIM Card '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $simcardType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o modelo de SIM Card "
              . "{$simcardType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de SIM Card "
        . "código {simcardTypeID} para remoção.",
        [ 'simcardTypeID' => $simcardTypeID ]
      );
      
      $message = "Não foi possível localizar o modelo de SIM Card para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de SIM Card ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $simcardTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de SIM Card. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de SIM Card ID {id}. Erro interno: {error}.",
        [ 'id' => $simcardTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de SIM Card. Erro "
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
