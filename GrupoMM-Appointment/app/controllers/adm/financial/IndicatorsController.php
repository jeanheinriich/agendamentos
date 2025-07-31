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
 * O controlador do gerenciamento de indicadores financeiros.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Financial;

use App\Models\Indicator;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class IndicatorsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de indicadores financeiros.
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
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Indicadores',
      $this->path('ADM\Financial\Indicators')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de indicadores financeiros.");
    
    // Recupera os dados da sessão
    $indicator = $this->session->get('indicator',
      [ 'searchField' => 'name',
        'searchValue' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/indicators.twig',
      [ 'indicator' => $indicator ])
    ;
  }

  /**
   * Recupera a relação dos indicadores financeiros em formato JSON.
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
    $this->debug("Acesso à relação de indicadores financeiros.");
    
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
    $searchField = $postParams['searchField'];
    $searchValue = $postParams['searchValue'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('indicator',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $IndicatorQry = Indicator::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($searchValue)) {
        switch ($searchField) {
          case 'name':
            // Filtra por parte do nome
            $IndicatorQry
              ->whereRaw("public.unaccented(name) ILIKE "
                  . "'%{$searchValue}%'"
                );

            break;
          default:
            // Filtra pelo campo indicado
            $IndicatorQry
              ->where(strtolower($searchField), $searchValue)
            ;
        }
      }

      // Conclui nossa consulta
      $indicators = $IndicatorQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'indicatorid AS id',
            'name',
            'institute',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($indicators) > 0) {
        $rowCount = $indicators[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $indicators
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos indicadores financeiros cadastrados.";
        } else {
          switch ($searchField)
          {
            case 'indicatorID':
              $error = "Não temos indicadores financeiros cujo código contém "
                . "<i>{$searchValue}</i>"
              ;

              break;
            case 'name':
              $error = "Não temos indicadores financeiros cujo nome contém "
                . "<i>{$searchValue}</i>"
              ;

              break;
          }
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'indicadores financeiros',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "indicadores financeiros. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'indicadores financeiros',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "indicadores financeiros. Erro interno."
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
   * Exibe um formulário para adição de um indicador financeiro, quando
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
      $this->debug("Processando à adição de um indicador financeiro.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notEmpty()
          ->length(2, 10)
          ->setName('Sigla do indicador financeiro'),
        'institute' =>  V::notEmpty()
          ->length(3, 10)
          ->setName('Sigla do instituto')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do indicador financeiro
          $indicatorData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um indicador financeiro com
          // a mesma sigla
          if (Indicator::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$$indicatorData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo indicador financeiro
            $indicator = new Indicator();
            $indicator->fill($indicatorData);
            $indicator->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o indicador financeiro '{name}' "
              .  "com sucesso.",
              [ 'name'  => $indicatorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O indicador financeiro "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $indicatorData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Financial\Indicators' ]
            );
            
            // Redireciona para a página de gerenciamento de indicadores
            // financeiros
            return $this->redirect($response, 'ADM\Financial\Indicators');
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "indicador financeiro '{name}'. Já existe um indicador "
              . "financeiro com o mesmo código.",
              [ 'name'  => $indicatorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um indicador "
              . "financeiro com a sigla <i>'{name}'</i>.",
              [ 'name'  => $indicatorData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "indicador financeiro '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $indicatorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do indicador financeiro. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "indicador financeiro '{name}'. Erro interno: {error}.",
            [ 'name'  => $indicatorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do indicador financeiro. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um indicador financeiro
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Indicadores',
      $this->path('ADM\Financial\Indicators')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Financial\Indicators\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de indicador financeiro.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/indicator.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }

  /**
   * Exibe um formulário para edição de um indicador financeiro, quando
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
      // Recupera as informações do indicador financeiro
      $indicatorID = $args['indicatorID'];
      $indicator = Indicator::findOrFail($indicatorID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do indicador financeiro "
          . "'{name}'.",
          [ 'name' => $indicator['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'indicatorid' => V::intVal()
            ->setName('ID do indicador financeiro'),
          'name' => V::notEmpty()
            ->length(2, 10)
            ->setName('Sigla do indicador financeiro'),
          'institute' =>  V::notEmpty()
            ->length(3, 10)
            ->setName('Sigla do instituto')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do indicador financeiro
            $indicatorData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos a sigla do indicador
            $save = false;
            if ($indicator->name != $indicatorData['name']) {
              // Modificamos a sigla, então verifica se temos um
              // indicador financeiro com a mesma sigla antes de
              // prosseguir
              if (Indicator::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$indicatorData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do indicador financeiro '{name}'. Já "
                  . "existe um indicador financeiro com a mesma sigla.",
                  [ 'name'  => $indicatorData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um indicador "
                  . "financeiro com a sigla <i>'{name}'</i>.",
                  [ 'name'  => $indicatorData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do indicador financeiro
              $indicator->fill($indicatorData);
              $indicator->save();
              
              // Registra o sucesso
              $this->info("Modificado o indicador financeiro '{name}' "
                . "com sucesso.",
                [ 'name'  => $indicatorData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O indicador financeiro "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $indicatorData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Financial\Indicators' ]
              );

              // Redireciona para a página de gerenciamento de
              // indicadores financeiros
              return $this->redirect($response, 'ADM\Financial\Indicators');
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "indicador financeiro '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'name'  => $indicatorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do indicador financeiro. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do indicador financeiro '{name}'. Erro interno: "
              . "{error}.",
              [ 'name'  => $indicatorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do indicador financeiro. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($indicator->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o indicador financeiro "
        . "código {indicatorID}.",
        [ 'indicatorID' => $indicatorID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este indicador "
        . "financeiro.");
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Financial\Indicators' ]
      );
      
      // Redireciona para a página de gerenciamento de indicadores financeiros
      return $this->redirect($response, 'ADM\Financial\Indicators');
    }
    
    // Exibe um formulário para edição de um indicador financeiro
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Indicadores',
      $this->path('ADM\Financial\Indicators')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Financial\Indicators\Edit', ['indicatorID' => $indicatorID])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do indicador financeiro '{name}'.",
      [ 'name' => $indicator->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/indicator.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }
  
  /**
   * Remove o indicador financeiro.
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
    $this->debug("Processando à remoção de indicador financeiro.");
    
    // Recupera o ID
    $indicatorID = $args['indicatorID'];

    try
    {
      // Recupera as informações do indicador financeiro
      $indicator = Indicator::findOrFail($indicatorID);
      
      // Agora apaga o indicador financeiro
      $indicator->delete();
      
      // Registra o sucesso
      $this->info("O indicador financeiro '{name}' foi removido com "
        . "sucesso.",
        [ 'indicatorID' => $indicator->indicatorid,
          'name' => $indicator->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o indicador financeiro "
              . "{$indicator->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o indicador financeiro "
        . "código {indicatorID} para remoção.",
        [ 'indicatorID' => $indicatorID ]
      );
      
      $message = "Não foi possível localizar o indicador financeiro "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "indicador financeiro ID {id}. Erro interno no banco de "
        . "dados: {error}.",
        [ 'id' => $indicatorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o indicador financeiro. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "indicador financeiro ID '{id}'. Erro interno: {error}.",
        [ 'id' => $indicatorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o indicador financeiro. "
        . "Erro interno."
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
