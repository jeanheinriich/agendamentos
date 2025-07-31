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
 * O controlador do gerenciamento de dias de vencimento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Models\DueDay;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class DueDaysController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(bool $addition = false): array
  {
    $validationRules = [
      'duedayid' => V::notBlank()
        ->intVal()
        ->setName('ID do dia de vencimento'),
      'day' => V::notBlank()
        ->intVal()
        ->between(1, 31)
        ->setName('Dia de vencimento')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['duedayid']);
    }

    return $validationRules;
  }

  /**
   * Exibe a página inicial do gerenciamento de dias de vencimento.
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
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Dias de vencimento',
      $this->path('ERP\Parameterization\Financial\DueDays')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de dias de vencimento.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/duedays/duedays.twig'
    );
  }
  
  /**
   * Recupera a relação dos dias de vencimento em formato JSON.
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
    $this->debug("Acesso à relação de dias de vencimento.");
    
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
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Realiza nossa consulta
      $dueDays = DueDay::where('contractorid',
            '=', $this->authorization->getContractor()->id
          )
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'duedayid AS id',
            'day',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($dueDays) > 0) {
        $rowCount = $dueDays[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $dueDays
            ])
        ;
      } else {
        $error = "Não temos dias de vencimento cadastrados.";
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'dias de vencimento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de dias de "
        . "vencimento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'dias de vencimento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de dias de "
        . "vencimento. Erro interno."
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
   * Exibe um formulário para adição de um dia de vencimento, quando
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

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de dia de vencimento.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do dia de vencimento
          $dueDayData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um dia de vencimento com
          // o mesmo dia neste contratante
          if (DueDay::where("contractorid",
                  '=', $contractor->id)
                ->where("day", '=', $dueDayData['day'])
                ->count() === 0) {
            // Grava o novo dia de vencimento
            $dueDay = new DueDay();
            $dueDay->fill($dueDayData);
            // Adiciona o contratante
            $dueDay->contractorid = $contractor->id;
            $dueDay->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o dia de vencimento '{day}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'day'  => $dueDayData['day'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O dia de vencimento <i>'{day}'"
              . "</i> foi cadastrado com sucesso.",
              [ 'day'  => $dueDayData['day'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\DueDays' ]
            );
            
            // Redireciona para a página de gerenciamento de dias de
            // vencimento
            return $this->redirect($response,
              'ERP\Parameterization\Financial\DueDays')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "dia de vencimento '{day}' do contratante "
              . "'{contractor}'. Já existe este dia de vencimento.",
              [ 'day'  => $dueDayData['day'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Este dia de vencimento já está "
              . "em uso.",
              [ 'day'  => $dueDayData['day'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "dia de vencimento '{day}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [ 'day'  => $dueDayData['day'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do dia de vencimento. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "dia de vencimento '{day}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [ 'day'  => $dueDayData['day'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do dia de vencimento. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyDueDay = [
        'day' => '1'
      ];
      $this->validator->setValues($emptyDueDay);
    }
    
    // Exibe um formulário para adição de um dia de vencimento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Dias de vencimento',
      $this->path('ERP\Parameterization\Financial\DueDays')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\DueDays\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de dia de vencimento no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/duedays/dueday.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um dia de vencimento, quando
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
    
    try
    {
      // Recupera as informações do dia de vencimento
      $dueDayID = $args['dueDayID'];
      $dueDay = DueDay::where('duedays.contractorid', '=', $contractor->id)
        ->where('duedays.duedayid', '=',
            $dueDayID
          )
        ->get([
            'duedays.*'
          ])
      ;

      if ( $dueDay->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum dia de "
          . "vencimento com o código {$dueDayID} cadastrado"
        );
      }
      $dueDay = $dueDay
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o dia de vencimento "
        . "código {duedayID}.",
        [ 'duedayID' => $dueDayID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este dia de "
        . "vencimento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\DueDays' ]
      );
      
      // Redireciona para a página de gerenciamento de dias de
      // vencimento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\DueDays')
      ;
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do dia de vencimento '{day}' "
        . "no contratante {contractor}.",
        [ 'day' => $dueDay['day'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados modificados do dia de vencimento
          $dueDayData = $this->validator->getValues();
          
          // Primeiro, verifica se não mudamos o dia de vencimento
          $save = false;
          if ($dueDay['day'] != $dueDayData['day']) {
            // Modificamos o dia de vencimento, então verifica se temos
            // um dia de vencimento no mesmo dia neste contratante antes
            // de prosseguir
            if (DueDay::where("contractorid", '=',
                      $contractor->id
                    )
                  ->where("day", '=', $dueDayData['day'])
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as informações "
                . "do dia de vencimento '{day}' para '{newday}' no "
                . "contratante '{contractor}'. O novo dia já está em "
                . "uso.",
                [ 'day'  => $dueDay['day'],
                  'newday'  => $dueDayData['day'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "O dia informado já está em uso");
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Grava as informações do dia de vencimento
            $dueDayChanged = DueDay::findOrFail($dueDayID);
            $dueDayChanged->fill($dueDayData);
            $dueDayChanged->save();
            
            // Registra o sucesso
            $this->info("Modificado o dia de vencimento de '{day}' para "
              . "'{newday}' no contratante '{contractor}' com sucesso.",
              [ 'day'  => $dueDay['day'],
                'newday'  => $dueDayData['day'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O dia de vencimento foi modificado "
              . "de {day} para {newday} com sucesso.",
              [ 'day'  => $dueDay['day'],
                'newday'  => $dueDayData['day'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\DueDays' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // parcelamentos
            return $this->redirect($response,
              'ERP\Parameterization\Financial\DueDays')
            ;
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do dia de vencimento '{day}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}",
            [ 'day'  => $dueDay['day'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do dia de vencimento. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do dia de vencimento '{day}' no contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'day'  => $dueDay['day'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do dia de vencimento. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($dueDay);
    }
    
    // Exibe um formulário para edição de um dia de vencimento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Dias de vencimento',
      $this->path('ERP\Parameterization\Financial\DueDays')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\DueDays\Edit',
        [ 'dueDayID' => $dueDayID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do dia de vencimento '{day}' do "
      . "contratante '{contractor}'.",
      [ 'day' => $dueDay['day'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/duedays/dueday.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }
  
  /**
   * Remove o dia de vencimento.
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
    $this->debug("Processando à remoção de dia de vencimento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $dueDayID = $args['dueDayID'];

    try
    {
      // Recupera as informações do dia de vencimento
      $dueDay = DueDay::where('contractorid',
            '=', $contractor->id
          )
        ->where('duedayid', '=', $dueDayID)
        ->firstOrFail()
      ;

      // Verifica se o dia de vencimento está em uso
      //if (BillingType::where("contractorid", '=', $contractor->id)
      //      ->where("duedayid", '=', $dueDayID)
      //      ->count() === 0) {
      //  // Agora apaga o dia de vencimento
      //  $dueDay->delete();
      //  
      //  // Registra o sucesso
      //  $this->info("O dia de vencimento '{day}' do contratante "
      //    . "'{contractor}' foi removido com sucesso.",
      //    [ 'name' => $dueDay->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  // Informa que a remoção foi realizada com sucesso
      //  return $response
      //    ->withHeader('Content-type', 'application/json')
      //    ->withJson([
      //        'result' => 'OK',
      //        'params' => $request->getParams(),
      //        'message' => "Removido o dia de vencimento "
      //          . "{$dueDay->name}",
      //        'data' => "Delete"
      //      ])
      //  ;
      //} else {
      //  // Registra o erro
      //  $this->error("Não foi possível remover as informações do tipo "
      //    . "de parcelamento '{day}' no contratante '{contractor}'. O "
      //    . "dia de vencimento está em uso.",
      //    [ 'name'  => $dueDay->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  $message = "Não foi possível remover o dia de vencimento, "
      //    . "pois o mesmo esta em uso."
      //  ;
      //}
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o dia de vencimento "
        . "código {dueDayID} para remoção.",
        [ 'dueDayID' => $dueDayID ]
      );
      
      $message = "Não foi possível localizar o dia de vencimento para "
      . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do dia de "
        . "vencimento '{day}' no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'name'  => $dueDay->day,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o dia de vencimento. Erro "
      . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do dia de "
        . "vencimento '{day}' no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'name'  => $dueDay->day,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o dia de vencimento. Erro "
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
