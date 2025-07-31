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
 * O controlador do gerenciamento de valores acumulados nos últimos doze
 * meses para cada indicador financeiro.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Financial;

use App\Models\Indicator;
use App\Models\AccumulatedValue;
use App\Models\Month;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\HTTP\Progress\ServerSentEvent;
use Core\Streams\ServerSentEventHandler;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class AccumulatedValuesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de valores acumulados nos
   * últimos doze meses de cada indicador financeiro.
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
    $this->breadcrumb->push('Valores acumulados nos últimos 12 meses',
      $this->path('ADM\Financial\Indicators\AccumulatedValues')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de valores acumulados dos "
      . "indicadores financeiros."
    );

    // Recupera as informações de meses do ano
    $months = Month::get();

    // Recupera as informações de indicadores financeiros
    $indicators = Indicator::orderBy('name')
      ->get([
          'indicatorid AS id',
          'name',
          'institute'
        ])
    ;
    
    // Recupera os dados da sessão
    $accumulatedValue = $this->session->get('accumulatedValue',
      [ 'indicatorID' => '0',
        'month' => '0',
        'year' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/accumulatedvalues/accumulatedvalues.twig',
      [ 'accumulatedValue' => $accumulatedValue,
        'indicators'  => $indicators,
        'months'  => $months ])
    ;
  }

  /**
   * Recupera a relação dos valores acumulados nos últimos 12 meses dos
   * indicadores financeiros em formato JSON.
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
    $this->debug("Acesso à relação de valores acumulados dos "
      . "indicadores financeiros."
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
    
    // Os campos de pesquisa selecionados
    $indicatorID = $postParams['indicatorID'];
    $indicatorName = $postParams['indicatorName'];
    $month = $postParams['month'];
    $year = $postParams['year'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('accumulatedValue',
      [ 'indicatorID' => $indicatorID,
        'month' => $month,
        'year' => $year ]
    );

    // Recupera as informações de meses do ano
    $months = Month::getAsArray();
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $AccumulatedValueQry = AccumulatedValue::join('indicators',
            'accumulatedvalues.indicatorid', '=',
            'indicators.indicatorid'
          )
      ;
      
      // Acrescenta os filtros
      if (!empty($indicatorID))
      {
        $AccumulatedValueQry
          ->where('accumulatedvalues.indicatorid', '=', $indicatorID)
        ;
      }
      if (!empty($month))
      {
        $AccumulatedValueQry
          ->where('accumulatedvalues.month', '=', $month)
        ;
      }
      if (!empty($year))
      {
        $AccumulatedValueQry
          ->where('accumulatedvalues.year', '=', $year)
        ;
      }

      // Conclui nossa consulta
      $accumulatedValues = $AccumulatedValueQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'accumulatedvalues.accumulatedvalueid AS id',
            'accumulatedvalues.indicatorid',
            'indicators.name',
            'indicators.institute',
            'accumulatedvalues.year',
            'accumulatedvalues.month',
            'accumulatedvalues.value',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($accumulatedValues) > 0) {
        $rowCount = $accumulatedValues[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $accumulatedValues
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($indicatorID), empty($month), empty($year))) {
          case 1:
            // Informado apenas o indicador financeiro
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para o indicador '{$indicatorName}'."
            ;

            break;
          case 2:
            // Informado apenas o mês
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para os indicadores nos mêses de "
              . $months[ $month ] . "."
            ;

            break;
          case 3:
            // Informado o indicador financeiro e o mês
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para o indicador '{$indicatorName}' nos mêses "
              . "de " . $months[ $month ] . "."
            ;

            break;
          case 4:
            // Informado o ano
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para os indicadores financeiros no ano de "
              . "{$year}."
            ;

            break;
          case 5:
            // Informado o ano e o indicador financeiro
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para o indicador '{$indicatorName}' no ano de "
              . "{$year}."
            ;

            break;
          case 6:
            // Informado o ano e o mês
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para os indicadores no mês de referência de "
              . $months[ $month ] . "/{$year}."
            ;

            break;
          case 7:
            // Informado o indicador financeiro, o ano e o mês
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para o indicador '{$indicatorName}' no mês de "
              . "referência de " . $months[ $month ] . "/{$year}."
            ;

            break;
          default:
            $error = "Não temos valores acumulados nos últimos 12 "
              . "meses para os indicadores financeiros cadastrados."
            ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'valores acumulados',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "valores acumulados. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'valores acumulados',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "valores acumulados. Erro interno."
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
   * Exibe um formulário para adição de um valor acumulado nos últimos
   * doze meses para um indicador financeiro, quando solicitado, e
   * confirma os dados enviados.
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
    // Recupera as informações de meses do ano
    $months = Month::get();

    // Recupera as informações de indicadores financeiros
    $indicators = Indicator::orderBy('name')
      ->get([
          'indicatorid AS id',
          'name',
          'institute'
        ])
    ;
    
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Recupera as informações de meses do ano
      $monthName = Month::getAsArray();

      // Recupera as informações de indicadores
      $indicatorName = [];
      foreach ($indicators as $indicator) {
        $indicatorName[$indicator->id] = "{$indicator->name} "
          . "($indicator->institute)"
        ;
      }
      
      // Registra o acesso
      $this->debug("Processando à adição de um valor acumulado para um "
        . "indicador financeiro."
      );
      
      // Valida os dados
      $this->validator->validate($request, [
        'indicatorid' => V::intVal()
          ->setName('Indicador financeiro'),
        'month' => V::notBlank()
          ->intVal()
          ->between(1, 12)
          ->setName('Mês'),
        'year' => V::notEmpty()
          ->length(4, null)
          ->setName('Ano'),
        'value' => V::numericValue()
          ->setName('Valor acumulado')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do indicador financeiro
          $accumulatedValueData = $this->validator->getValues();

          // Primeiro, verifica se não temos um valor acumulado para o
          // indicador financeiro neste mês de referência
          if (AccumulatedValue::where("indicatorid",
                  '=', $accumulatedValueData['indicatorid'])
                ->where("month", $accumulatedValueData['month'])
                ->where("year", $accumulatedValueData['year'])
                ->count() === 0) {
            // Grava o novo valor acumulado no mês de referência para
            // este indicador financeiro
            $accumulatedValue = new AccumulatedValue();
            $accumulatedValue->fill($accumulatedValueData);
            $accumulatedValue->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o valor acumulado nos últimos "
              . "meses para o indicador '{name}' no mês de "
              . "{month}/{year} com sucesso.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O valor acumulado nos últimos "
              . "meses para o indicador <i>{name}</i> no mês de "
              . "{month}/{year} foi cadastrado com sucesso.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Financial\Indicators\AccumulatedValues' ]
            );
            
            // Redireciona para a página de gerenciamento de valores
            // acumulador por indicador financeiro
            return $this->redirect($response,
              'ADM\Financial\Indicators\AccumulatedValues'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "valor acumulado para o indicador '{name}' no mês de "
              . "{month}/{year}. Já existe um valor acumulado "
              . "registrado para este período.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um valor acumulado "
              . "nos últimos 12 meses para o indicador <i>{name}</i> "
              . "no mês de {month}/{year}.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "valor acumulado para o indicador '{name}' no mês de "
            . "{month}/{year}. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
              'month'  => $monthName[$accumulatedValueData['month']],
              'year'  => $accumulatedValueData['year'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do valor acumulado para o indicador "
            . "<i>{name}</i> no mês de {month}/{year}. Erro interno no "
            . "banco de dados.",
            [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
              'month'  => $monthName[$accumulatedValueData['month']],
              'year'  => $accumulatedValueData['year'] ]
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "valor acumulado para o indicador '{name}' no mês de "
            . "{month}/{year}. Erro interno: {error}.",
            [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
              'month'  => $monthName[$accumulatedValueData['month']],
              'year'  => $accumulatedValueData['year'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do valor acumulado para o indicador "
            . "<i>{name}</i> no mês de {month}/{year}. Erro interno.",
            [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
              'month'  => $monthName[$accumulatedValueData['month']],
              'year'  => $accumulatedValueData['year'] ]
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um valor acumulado para um
    // indicador financeiro
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Indicadores',
      $this->path('ADM\Financial\Indicators')
    );
    $this->breadcrumb->push('Valores acumulados nos últimos 12 meses',
      $this->path('ADM\Financial\Indicators\AccumulatedValues')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Financial\Indicators\AccumulatedValues\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição do valor acumulado para um indicador "
      . "financeiro."
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/accumulatedvalues/accumulatedvalue.twig',
      [ 'formMethod' => 'POST',
        'indicators'  => $indicators,
        'months'  => $months ])
    ;
  }

  /**
   * Exibe um formulário para edição de um valor acumulado nos últimos
   * doze meses para um indicador financeiro, quando solicitado, e
   * confirma os dados enviados.
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
      $accumulatedValueID = $args['accumulatedValueID'];
      $accumulatedValue = AccumulatedValue::findOrFail($accumulatedValueID);

      // Recupera as informações de meses do ano
      $months = Month::get();

      // Recupera as informações de indicadores financeiros
      $indicators = Indicator::orderBy('name')
        ->get([
            'indicatorid AS id',
            'name',
            'institute'
          ])
      ;
      
      // Recupera as informações de meses do ano
      $monthName = Month::getAsArray();

      // Recupera as informações de indicadores
      $indicatorName = [];
      foreach ($indicators as $indicator) {
        $indicatorName[$indicator->id] = "{$indicator->name} "
          . "($indicator->institute)"
        ;
      }
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do valor acumulado no mês "
          . "de {month}/{year} para o indicador '{name}'.",
          [ 'month'  => $monthName[$accumulatedValue['month']],
            'year'  => $accumulatedValue['year'],
            'name' => $indicatorName[$accumulatedValue['indicatorid']] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'accumulatedvalueid' => V::intVal()
            ->setName('ID do valor acumulado'),
          'indicatorid' => V::intVal()
            ->setName('Indicador financeiro'),
          'month' => V::notBlank()
            ->intVal()
            ->between(1, 12)
            ->setName('Mês'),
          'year' => V::notEmpty()
            ->length(4, null)
            ->setName('Ano'),
          'value' => V::numericValue()
            ->setName('Valor acumulado')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do indicador financeiro
            $accumulatedValueData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o indicador e/ou o mês
            // ou ano de referência
            $save = false;
            if ( ($accumulatedValue->indicatorid != $accumulatedValueData['indicatorid'])
                 || ($accumulatedValue->month != $accumulatedValueData['month'])
                 || ($accumulatedValue->year != $accumulatedValueData['year']) ) {
              // Modificamos o valor acumulado, então verifica se temos
              // um valor acumulado para o indicador financeiro informado
              // neste mês de referência antes de prosseguir
              if (AccumulatedValue::where("indicatorid",
                      '=', $accumulatedValueData['indicatorid'])
                    ->where("month", $accumulatedValueData['month'])
                    ->where("year", $accumulatedValueData['year'])
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as informações "
                  . "do valor acumulado para o indicador '{name}' no "
                  . "mês de {month}/{year}. Já existe um valor "
                  . "registrado para este indicador neste mesmo mês de "
                  . "referência.",
                  [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                    'month'  => $monthName[$accumulatedValueData['month']],
                    'year'  => $accumulatedValueData['year'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um valor acumulado "
                  . "registrado para este indicador neste mesmo mês de "
                  . "referência."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do indicador financeiro
              $accumulatedValue->fill($accumulatedValueData);
              $accumulatedValue->save();
              
              // Registra o sucesso
              $this->info("Modificado o valor acumulado nos últimos "
                . "meses para o indicador '{name}' no mês de "
                . "{month}/{year} com sucesso.",
                [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                  'month'  => $monthName[$accumulatedValueData['month']],
                  'year'  => $accumulatedValueData['year'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O valor acumulado nos últimos "
                . "meses para o indicador <i>{name}</i> no mês de "
                . "{month}/{year} foi modificado com sucesso.",
                [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                  'month'  => $monthName[$accumulatedValueData['month']],
                  'year'  => $accumulatedValueData['year'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Financial\Indicators\AccumulatedValues' ]
              );

              // Redireciona para a página de gerenciamento de valores
              // acumulador por indicador financeiro
              return $this->redirect($response,
                'ADM\Financial\Indicators\AccumulatedValues'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->debug("Não foi possível modificar as informações do "
              . "valor acumulado para o indicador '{name}' no mês de "
              . "{month}/{year}. Erro interno no banco de dados: "
              . "{error}.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do valor acumulado para o indicador "
              . "<i>{name}</i> no mês de {month}/{year}. Erro interno no "
              . "banco de dados.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->debug("Não foi possível modificar as informações do "
              . "valor acumulado para o indicador '{name}' no mês de "
              . "{month}/{year}. Erro interno: {error}.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do valor acumulado para o indicador "
              . "<i>{name}</i> no mês de {month}/{year}. Erro interno.",
              [ 'name'  => $indicatorName[$accumulatedValueData['indicatorid']],
                'month'  => $monthName[$accumulatedValueData['month']],
                'year'  => $accumulatedValueData['year'] ]
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($accumulatedValue->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o valor acumulado "
        . "código {accumulatedValueID}.",
        [ 'accumulatedValueID' => $accumulatedValueID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este valor "
        . "acumulado.");
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Financial\Indicators\AccumulatedValues' ]
      );
      
      // Redireciona para a página de gerenciamento de valores
      // acumulados por indicador financeiro
      return $this->redirect($response,
        'ADM\Financial\Indicators\AccumulatedValues'
      );
    }
    
    // Exibe um formulário para edição de um valor acumulado para um
    // indicador financeiro
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Indicadores',
      $this->path('ADM\Financial\Indicators')
    );
    $this->breadcrumb->push('Valores acumulados nos últimos 12 meses',
      $this->path('ADM\Financial\Indicators\AccumulatedValues')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Financial\Indicators\AccumulatedValues\Edit',
        ['accumulatedValueID' => $accumulatedValueID]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do valor acumulado para o indicador "
      . "'{name}' no mês de {month}/{year}.",
      [ 'name' => $indicatorName[$accumulatedValue['indicatorid']],
        'month' => $monthName[$accumulatedValue['month']],
        'year' => $accumulatedValue['year'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/indicators/accumulatedvalues/accumulatedvalue.twig',
      [ 'formMethod' => 'PUT',
        'indicators'  => $indicators,
        'months'  => $months ]
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
    $this->debug("Processando à remoção do valor acumulado para um "
      . "indicador financeiro."
    );
    
    // Recupera o ID
    $accumulatedValueID = $args['accumulatedValueID'];

    // Recupera as informações de meses do ano
    $monthName = Month::getAsArray();

    try
    {
      // Recupera as informações do valor acumulado
      $accumulatedValue = AccumulatedValue::findOrFail($accumulatedValueID);

      // Recupera a informação do indicador financeiro
      $indicator = Indicator::findOrFail($accumulatedValue->indicatorid);
      
      // Agora apaga o indicador financeiro
      $accumulatedValue->delete();
      
      // Registra o sucesso
      $this->info("O valor acumulado para o indicador '{name}' no mês "
        . "de {month}/{year} foi removido com sucesso.",
        [ 'name'  => "{$indicator->name} ($indicator->institute)",
          'month'  => $monthName[$accumulatedValue->month],
          'year'  => $accumulatedValue->year ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o valor acumulado para o indicador "
              . "'{$indicator->name} ($indicator->institute)' no mês "
              . "de {$accumulatedValue->month}/{$accumulatedValue->year}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o valor acumulado "
        . "código {accumulatedValueID} para remoção.",
        [ 'accumulatedValueID' => $accumulatedValueID ]
      );
      
      $message = "Não foi possível localizar o valor acumulado "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do valor "
        . "acumulado para o indicador ID {id}. Erro interno no banco "
        . "de dados: {error}.",
        [
          'id'  => $accumulatedValueID,
          'error'  => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o valor acumulado para o "
        . "indicador. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do valor "
        . "acumulado para o indicador ID {id}. Erro interno: {error}.",
        [ 'id'  => $accumulatedValueID,
          'error'  => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o valor acumulado para o "
        . "indicador. Erro interno."
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

  /**
   * Atualiza os valores dos indicadores financeiros utilizando um
   * provedor externo, fazendo as devidas modificações na base de dados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function update(Request $request, Response $response)
  {
    // Registra o acesso
    $this->info("Processando a atualização de indicadores financeiros.");

    // Recuperamos as configurações de integração ao provedor de dados
    // de indicadores financeiros
    $settings      = $this->container['settings']['integration']['indicators'];
    $providerName  = $settings['provider'];
    $path          = $settings['path'];
    $providerClass = "Core\\FinancialIndicators\\Providers\\"
      . $providerName
    ;

    // Criamos o mecanismo para envio de eventos para o cliente
    $serverEvent = new ServerSentEvent();

    if (class_exists($providerClass)) {
      $provider = new $providerClass($path);

      // Recupera as informações de indicadores financeiros
      $indicators = Indicator::orderBy('name')
        ->get([
            'indicatorid AS id',
            'name',
            'institute'
          ])
      ;

      // Constrói um manipulador de eventos enviados pelo servidor (SSE)
      // para lidar com o progresso do processamento
      $output = new ServerSentEventHandler(function ()
        use ($serverEvent, $provider, $indicators)
      {
        try {
          // Inicializamos nosso acompanhamento
          $done = 0;
          $total = count($indicators);
          $serverEvent->send('START', $done, $total, 'Iniciando...');
          
          // Recupera as informações de meses do ano
          $monthName = Month::getAsArray();

          // Percorremos todos os indicadores financeiros, e atualizamos
          // as informações de cada indicador
          foreach ($indicators as $iNum => $indicator) {
            // Registra o processamento
            $this->info("Atualizando os valores do indicador {name}.",
              [ 'name'  => "{$indicator->name} ($indicator->institute)" ]
            );

            $done = $iNum;
            $serverEvent->send('PROGRESS', $done, $total,
              'Atualizando indicadores...'
            );

            $indicatorIndexes = $provider->getIndexesFromIndicatorCode($indicator->id);
            $amount = count($indicatorIndexes);

            // Percorre todos os índices fornecidos, inserindo àqueles
            // que ainda não existam no banco de dados
            foreach ($indicatorIndexes as $row => $indicatorIndex) {
              $month = $indicatorIndex->getDate()->format('n');
              $year  = $indicatorIndex->getDate()->format('Y');

              $done = $iNum
                + ((($row + 1) * 100) / $amount) / 100
              ;
              $serverEvent->send('PROGRESS', $done, $total,
                'Atualizando indicadores...'
              );

              if (AccumulatedValue::where("indicatorid",
                      '=', $indicator->id)
                    ->where("month", $month)
                    ->where("year", $year)
                    ->count() === 0) {
                // Grava o novo valor acumulado no mês de referência
                // para este indicador financeiro
                $accumulatedValue = new AccumulatedValue();
                $accumulatedValue->indicatorid = $indicator->id;
                $accumulatedValue->month = $month;
                $accumulatedValue->year = $year;
                $accumulatedValue->value = $indicatorIndex->getPercentage();
                $accumulatedValue->save();
                
                // Registra o sucesso
                $this->info("Cadastrado o valor acumulado nos últimos "
                  . "meses para o indicador '{name}' no mês de "
                  . "{month}/{year} com sucesso.",
                  [ 'name'  => "{$indicator->name} ($indicator->institute)",
                    'month'  => $monthName[$month],
                    'year'  => $year ]
                );
              }
            }
          }

          $done = $total;
          $serverEvent->send('END', $done, $total,
            'Atualizando indicadores...'
          );
        }
        catch (Throwable $error)
        {
          $this->error($error->getMessage());
          $serverEvent->send('ERROR', 0, 0, $error->getMessage());
        }

        return '';
      });
      
      return $response
        ->withHeader('Content-Type', 'text/event-stream')
        ->withHeader('Cache-Control', 'no-cache')
          // Desativa o buffer FastCGI no Nginx
        ->withHeader('X-Accel-Buffering', 'no')
        ->withBody($output)
      ;
    }
  }
}
