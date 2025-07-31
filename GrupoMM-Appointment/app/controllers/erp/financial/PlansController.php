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
 * O controlador do gerenciamento de planos de serviços.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\BillingType;
use App\Models\Feature;
use App\Models\Indicator;
use App\Models\MeasureType;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanCharge;
use App\Models\SubscriptionPlan;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use RuntimeException;

class PlansController
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
  protected function getValidationRules(
    bool $addition = false
  ): array
  {
    $validationRules = [
      'planid' => V::notBlank()
        ->intVal()
        ->setName('ID do plano de serviço'),
      'name' => V::notBlank()
        ->length(2, 50)
        ->setName('Nome do plano'),
      'description' => V::notBlank()
        ->setName('Descrição'),
      'monthprice' => V::numericValue()
        ->minimumValue('1,00')
        ->setName('Mensalidade'),
      'duration' => V::intVal()
        ->between(1, 99)
        ->setName('Duração do contrato'),
      'indicatorid' => V::notBlank()
        ->intVal()
        ->setName('Indicador financeiro'),
      'readjustmentperiod' => V::intVal()
        ->between(0, 96)
        ->setName('Período entre reajustes'),
      'active' => V::boolVal()
        ->setName('Plano de serviço ativo'),
      'subscriptions' => [
        'subscriptionplanid' => V::intVal()
          ->setName('ID do plano de assinatura'),
        'numberofmonths' => V::intVal()
          ->min(1)
          ->setName('Tempo da assinatura'),
        'discountrate' => V::numericValue()
          ->setName('Taxa de desconto'),
        'monthprice' => V::numericValue()
          ->setName('Mensalidade'),
        'total' => V::numericValue()
          ->setName('Valor pago')
      ],
      'loyaltyperiod' => V::intVal()
        ->between(0, 12)
        ->setName('Período de fidelidade'),
      'loyaltyfine' => V::numericValue()
        ->setName('Multa pelo rompimento antecipado'),
      'finevalue' => V::numericValue()
        ->setName('Valor da multa em caso de atraso no pagamento'),
      'arrearinteresttype' => V::intVal()
        ->setName('Tipo do valor dos juros de mora'),
      'arrearinterest' => V::numericValue()
        ->setName('Taxa diária dos juros de mora'),
      'readjustwithsinglevalue' => V::boolVal()
        ->setName('Reajustar instalações com um valor único'),
      'allowextendingdeadline' => V::boolVal()
        ->setName('Permitir estender prazo de boletos vencidos'),
      'prorata' => V::boolVal()
        ->setName('Permitir cobrança proporcional aos dias contratados (Prorata)'),
      'duedateonlyinworkingdays' => V::boolVal()
        ->setName('Permitir vencimento apenas em dias úteis'),
      'charges' => [
        'added' => V::boolVal()
          ->setName('Tipo de tarifa adicionado'),
        'planchargeid' => V::intVal()
          ->setName('ID do tipo de tarifa cobrada'),
        'billingtypeid' => V::intVal()
          ->min(1)
          ->setName('Tipo de cobrança'),
        'chargevalue' => V::numericValue()
          ->setName('Valor cobrado')
      ],
      'features' => [
        'added' => V::boolVal()
          ->setName('Característica técnica adicionada'),
        'planfeatureid' => V::intVal()
          ->setName('ID da característica técnica do plano'),
        'featureid' => V::notBlank()
          ->intVal()
          ->setName('ID da característica técnica')
      ],
      'drivingpositioninginterval' => V::notBlank()
        ->intVal()
        ->setName('O intervalo de transmissão com a ignição ligada'),
      'stoppedpositioninginterval' => V::notBlank()
        ->intVal()
        ->setName('O intervalo de transmissão com a ignição desligada')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['planid']);
      unset($validationRules['subscriptions']['subscriptionplanid']);
      unset($validationRules['charges']['planchargeid']);
      unset($validationRules['features']['planfeatureid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de unidades de medidas.
   *
   * @return Collection
   *   A matriz com as informações de unidades de medidas
   *
   * @throws RuntimeException
   *   Em caso de não termos unidades de medida
   */
  protected function getMeasureTypes(): Collection
  {
    try {
      // Recupera as informações de unidades de medidas
      $measureTypes = MeasureType::orderBy('measuretypeid')
        ->get([
            'measuretypeid AS id',
            'name',
            'symbol'
          ])
      ;

      if ( $measureTypes->isEmpty() ) {
        throw new Exception("Não temos nenhuma unidade de medida "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de unidades "
        . "de medidas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as unidades "
        . "de medidas"
      );
    }

    return $measureTypes;
  }

  /**
   * Recupera as informações de indicadores financeiros.
   *
   * @return Collection
   *   A matriz com as informações de indicadores financeiros
   *
   * @throws RuntimeException
   *   Em caso de não termos indicadores financeiros
   */
  protected function getIndicators(): Collection
  {
    try {
      // Recupera as informações de indicadores financeiros
      $indicators = Indicator::orderBy("name")
        ->get([
            'indicatorid as id',
            'name',
            'institute'
          ])
      ;

      if ( $indicators->isEmpty() ) {
        throw new Exception("Não temos nenhum indicador financeiro "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "indicadores financeiros. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os "
        . "indicadores financeiros"
      );
    }

    return $indicators;
  }

  /**
   * Recupera as informações de tipos de cobranças.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os tipos de
   *   cobrança disponíveis
   *
   * @return Collection
   *   A matriz com as informações de tipos de cobranças
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de cobranças
   */
  protected function getBillingTypes(int $contractorID): Collection
  {
    try {
      // Recupera as informações de tipos de cobranças deste contratante
      $billingTypes = BillingType::leftJoin('installmenttypes', 'billingtypes.installmenttypeid',
            '=', 'installmenttypes.installmenttypeid'
          )
        ->where("billingtypes.contractorid",
            '=', $contractorID
          )
        ->orderBy('billingtypes.inattendance', 'ASC')
        ->orderBy('billingtypes.name', 'ASC')
        ->get([
            'billingtypes.billingtypeid as id',
            'billingtypes.name',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN billingtypes.inattendance THEN 2"
              . "  ELSE 1 "
              . "END AS group"),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN billingtypes.installmenttypeid > 0 THEN installmenttypes.name "
              . "  ELSE 'Não disponível' "
              . "END AS installment")
          ])
      ;

      if ( $billingTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de cobrança "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "cobranças. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "cobranças"
      );
    }

    return $billingTypes;
  }

  /**
   * Recupera as informações das características técnicas possíveis que
   * podem ser exigidas de rastreadores em um plano de serviços.
   *
   * @return array
   *   A matriz com as informações de características técnicas
   *
   * @throws RuntimeException
   *   Em caso de não termos características técnicas
   */
  protected function getFeaturesTable(): array
  {
    try {
      // Recupera as informações de características técnicas
      $features = Feature::orderBy("name")
        ->get([
            'featureid as id',
            'name'
          ])
      ;

      if ( $features->isEmpty() ) {
        throw new Exception("Não temos nenhuma característica técnica "
          . "cadastrada"
        );
      }

      // Divide as características técnicas em 3 colunas para melhorar a
      // legibilidade e diminuir o comprimento da página
      $featuresTable = [];
      $column = 0;
      foreach ($features as $feature) {
        $featuresTable[$column][] = $feature;
        $column++;
        if ($column == 3) {
          $column = 0;
        }
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "características técnicas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as "
        . "características técnicas"
      );
    }

    return $featuresTable;
  }

  /**
   * Exibe a página inicial do gerenciamento de planos de serviço.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Planos',
      $this->path('ERP\Financial\Plans')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de planos de serviço.");
    
    // Recupera os dados da sessão
    $plan = $this->session->get('plan',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/plans/plans.twig',
      [ 'plan' => $plan ])
    ;
  }

  /**
   * Recupera a relação dos planos de serviços em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Acesso à relação de planos.");
    
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
    $this->session->set('plan',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $PlanQry = Plan::join('indicators', 'plans.indicatorid', '=',
            'indicators.indicatorid'
          )
        ->where('plans.contractorid',
        '=', $this->authorization->getContractor()->id
      );
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $PlanQry
          ->whereRaw("public.unaccented(plans.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $plans = $PlanQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'plans.planid AS id',
            'plans.createdat',
            'plans.name',
            'plans.monthprice',
            'plans.duration',
            'plans.loyaltyperiod',
            'plans.indicatorid',
            'indicators.name AS indicatorname',
            'plans.readjustmentperiod',
            'plans.active',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($plans) > 0) {
        $rowCount = $plans[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $plans
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos planos de serviço cadastrados.";
        } else {
          $error = "Não temos planos de serviço cadastrados cujo nome "
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
        [ 'module' => 'planos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de planos. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'planos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de planos. "
        . "Erro interno."
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
   * Exibe um formulário para adição de um plano de serviços, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(
    Request $request,
    Response $response
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações dos indicadores financeiros
      $indicators = $this->getIndicators();

      // Recupera as informações de unidades de medidas
      $measureTypes = $this->getMeasureTypes();

      // Recupera as informações dos tipos de cobranças
      $billingTypes = $this->getBillingTypes($contractor->id);

      // Recupera as informações das características técnicas
      $featuresTable = $this->getFeaturesTable();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Plans' ]
      );

      // Redireciona para a página de gerenciamento de planos de serviços
      return $this->redirect($response, 'ERP\Financial\Plans');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de plano de serviço.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do plano são VÁLIDOS');

        // Recupera os dados do plano de serviços
        $planData = $this->validator->getValues();

        try
        {
          // Primeiro, verifica se não temos um plano com
          // o mesmo nome neste contratante
          if (Plan::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$planData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo plano
            
            // Precisa retirar dos parâmetros as informações
            // correspondentes às outras tabelas
            $subscriptionsData = $planData['subscriptions'];
            unset($planData['subscriptions']);
            $chargesData = $planData['charges'];
            unset($planData['charges']);
            $featuresData = $planData['features'];
            unset($planData['features']);
            
            // Iniciamos a transação
            $this->DB->beginTransaction();

            $plan = new Plan();
            $plan->fill($planData);
            // Adiciona o contratante e usuários atuais
            $plan->contractorid = $contractor->id;
            $plan->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $plan->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $plan->save();
            $planID = $plan->planid;
            
            // Incluímos todos os planos de assinatura
            foreach($subscriptionsData AS $subscriptionData) {
              // Incluímos um novo plano de assinatura
              $subscription = new SubscriptionPlan();
              $subscription->fill($subscriptionData);
              $subscription->planid = $planID;
              $subscription->save();
            }
            
            // Incluímos todos os valores cobrados neste plano
            foreach($chargesData AS $chargeData) {
              // Incluímos um novo valor cobrado deste plano
              $charge = new PlanCharge();
              $charge->fill($chargeData);
              $charge->planid = $planID;
              $charge->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->save();
            }
            
            // Incluímos todas as características técnicas
            foreach($featuresData AS $featureData) {
              // Incluímos uma nova característica técnica
              $feature = new PlanFeature();
              $feature->fill($featureData);
              $feature->planid = $planID;
              $feature->save();
            }

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o plano de serviços '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $planData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O plano de serviços <i>'{name}'"
              . "</i> foi cadastrado com sucesso.",
              [ 'name'  => $planData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Financial\Plans' ]
            );
            
            // Redireciona para a página de gerenciamento de planos
            return $this->redirect($response,
              'ERP\Financial\Plans')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "plano de serviços '{name}' do contratante "
              . "'{contractor}'. Já existe um plano com o mesmo nome.",
              [ 'name'  => $planData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um plano com o nome "
              . "<i>'{name}'</i>.",
              [ 'name'  => $planData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "plano de serviços '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $planData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do plano de serviços. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "plano de serviços '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $planData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do plano de serviços. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do plano são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyPlan = [
        'monthprice' => '0,00',
        'duration' => 12,
        'indicatorid' => 2,
        'readjustmentperiod' => 12,
        'active' => "true",
        'subscriptions' => [[
          'numberofmonths' => 1,
          'discountrate' => '0,0000',
          'monthprice' => '0,00',
          'total' => '0,00'
        ]],
        'loyaltyperiod' => '0',
        'loyaltyfine' => '0,0000',
        'finevalue' => '2,0000',
        'arrearinteresttype' => 2,
        'arrearinterest' => '0,0333',
        'readjustwithsinglevalue' => false,
        'allowextendingdeadline' => "false",
        'prorata' => "true",
        'duedateonlyinworkingdays' => "true",
        'charges' => [
          $billingTypes[1]['id'] => [
            'billingtypeid' => $billingTypes[1]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[2]['id'] => [
            'billingtypeid' => $billingTypes[2]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[3]['id'] => [
            'billingtypeid' => $billingTypes[3]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[4]['id'] => [
            'billingtypeid' => $billingTypes[4]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[5]['id'] => [
            'billingtypeid' => $billingTypes[5]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[6]['id'] => [
            'billingtypeid' => $billingTypes[6]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ],
          $billingTypes[7]['id'] => [
            'billingtypeid' => $billingTypes[7]['id'],
            'planchargeid' => 0,
            'chargevalue' => '0,00'
          ]
        ],
        'features' => [
          1 => [
            'added' => "true",
            'planfeatureid' => 0,
            'featureid' => 1
          ],
          7 => [
            'added' => "true",
            'planfeatureid' => 0,
            'featureid' => 7
          ],
          11 => [
            'added' => "true",
            'planfeatureid' => 0,
            'featureid' => 7
          ]
        ],
        'drivingpositioninginterval' => 1*60,
        'stoppedpositioninginterval' => 60*60
      ];

      $this->validator->setValues($emptyPlan);
    }
    
    // Exibe um formulário para adição de um plano
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Planos',
      $this->path('ERP\Financial\Plans')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Financial\Plans\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de plano de serviço no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/plans/plan.twig',
      [ 'formMethod' => 'POST',
        'indicators' => $indicators,
        'measureTypes' => $measureTypes,
        'billingTypes' => $billingTypes,
        'featuresTable' => $featuresTable ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um plano, quando
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
  public function edit(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações dos indicadores financeiros
      $indicators = $this->getIndicators();

      // Recupera as informações de unidades de medidas
      $measureTypes = $this->getMeasureTypes();

      // Recupera as informações dos tipos de cobranças
      $billingTypes = $this->getBillingTypes($contractor->id);

      // Recupera as informações das características técnicas
      $featuresTable = $this->getFeaturesTable();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Plans' ]
      );

      // Redireciona para a página de gerenciamento de planos de serviços
      return $this->redirect($response, 'ERP\Financial\Plans');
    }
    
    try
    {
      // Recupera as informações do plano
      $planID = $args['planID'];
      $plan = Plan::join('indicators', 'plans.indicatorid', '=',
            'indicators.indicatorid'
          )
        ->join('users AS createduser',
            'plans.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'plans.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('plans.contractorid', '=', $contractor->id)
        ->where('plans.planid', '=', $planID)
        ->get([
            'plans.*',
            'indicators.name AS indicatorname',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $plan->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum tipo de "
          . "cobrança com o código {$planID} cadastrado"
        );
      }
      $plan = $plan
        ->first()
        ->toArray()
      ;

      // Recupera as informações dos planos de assinatura disponíveis
      $subscriptions = SubscriptionPlan::join('plans', 'plans.planid',
            '=', 'subscriptionplans.planid'
          )
        ->where('subscriptionplans.planid', $planID)
        ->orderBy('subscriptionplans.numberofmonths')
        ->get([
          'subscriptionplans.subscriptionplanid',
          'subscriptionplans.numberofmonths',
          'subscriptionplans.discountrate',
          $this->DB->raw("REPLACE(TO_CHAR(plans.monthprice - (plans.monthprice * subscriptionplans.discountrate/100), 'FM9999999999D00'), '.', ',') AS monthprice"),
          $this->DB->raw("REPLACE(TO_CHAR(plans.monthprice*subscriptionplans.numberofmonths - (plans.monthprice * subscriptionplans.numberofmonths * subscriptionplans.discountrate/100), 'FM9999999999D00'), '.', ',') AS total")
        ])
      ;
      if ( $subscriptions->isEmpty() ) {
        // Criamos um plano de assinatura vazio
        $plan['subscriptions'] = [
          'subscriptionplanid' => 0,
          'numberofmonths' => 1,
          'discountrate' => '0,0000',
          'monthprice' => $plan['monthprice'],
          'total' => $plan['monthprice']
        ];
      } else {
        $plan['subscriptions'] = $subscriptions
          ->toArray()
        ;
      }

      // Recupera as informações dos valores cobrados
      $charges = PlanCharge::join('billingtypes',
            'plancharges.billingtypeid', '=',
            'billingtypes.billingtypeid'
          )
        ->where('plancharges.planid', $planID)
        ->orderBy('plancharges.planchargeid')
        ->get([
          'plancharges.*',
          'billingtypes.name AS billingtypename'
        ])
      ;

      // Precisamos organizar os tipos de cobrança pelo código do tipo
      // de forma a permitir renderizar corretamente na página
      $plan['charges'] = [ ];
      if ( !$charges->isEmpty() ) {
        foreach ($charges->toArray() as $charge) {
          $charge['added'] = 'true';
          $plan['charges'][$charge['billingtypeid']] = $charge;
        }
      }

      // Recupera as informações das características técnicas disponíveis
      $features = PlanFeature::join('features', 'features.featureid',
            '=', 'planfeatures.featureid'
          )
        ->where('planfeatures.planid', $planID)
        ->orderBy('features.name')
        ->get([
          'planfeatures.planfeatureid',
          'planfeatures.featureid',
          'features.name AS featurename'
        ])
      ;

      // Precisamos organizar as características técnicas pelo código da
      // característica de forma a permitir renderizar corretamente na
      // página
      $plan['features'] = [ ];
      if ( !$features->isEmpty() ) {
        foreach ($features->toArray() as $feature) {
          $feature['added'] = 'true';
          $plan['features'][$feature['featureid']] = $feature;
        }
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o plano de serviço "
        . "código {planID}.",
        [ 'planID' => $planID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este plano de "
        . "serviço."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Plans' ]
      );
      
      // Redireciona para a página de gerenciamento de planos de serviço
      return $this->redirect($response,
        'ERP\Financial\Plans'
      );
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do plano de serviço '{name}' "
        . "no contratante {contractor}.",
        [ 'name' => $plan['name'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do plano são VÁLIDOS');

        // Grava as informações no banco de dados

        // Recupera os dados modificados do plano
        $planData = $this->validator->getValues();

        try
        {
          // Primeiro, verifica se não mudamos o nome do tipo de
          // contrato
          $save = false;
          if ($plan['name'] != $planData['name']) {
            // Modificamos o nome do plano, então verifica
            // se temos um plano com o mesmo nome neste
            // contratante antes de prosseguir
            if (Plan::where("contractorid", '=',
                      $contractor->id
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$planData['name']}')"
                    )
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as "
                . "informações do plano '{name}' no "
                . "contratante '{contractor}'. Já existe um tipo de "
                . "contrato com o mesmo nome.",
                [ 'name'  => $planData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe um tipo de "
                . "contrato com o mesmo nome."
              );
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Grava as informações do plano

            // Precisa retirar dos parâmetros as informações
            // correspondentes às outras tabelas
            $subscriptionsData = $planData['subscriptions'];
            unset($planData['subscriptions']);
            $chargesData = $planData['charges'];
            unset($planData['charges']);
            $featuresData = $planData['features'];
            unset($planData['features']);

            // Não permite modificar o contratante
            unset($planData['contractorid']);

            // ============================[ Planos de assinatura ]=====
            // Recupera as informações dos planos de assinatura e separa
            // os dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================
            
            // Analisa os planos de assinatura informados, de forma a
            // separar quais valores precisam ser adicionados,
            // removidos e atualizados
            
            // Matrizes que armazenarão os dados dos planos de
            // assinatura a serem adicionados, atualizados e removidos
            $newSubscriptions = [ ];
            $updSubscriptions = [ ];
            $delSubscriptions = [ ];

            // Os IDs dos planos de assinatura mantidos para permitir
            // determinar àqueles a serem removidos
            $heldSubscriptions = [ ];

            // Determina quais planos de assinatura serão mantidos (e
            // atualizados) e os que precisam ser adicionados (novos)
            foreach ($subscriptionsData AS $subscription) {
              if (empty($subscription['subscriptionplanid'])) {
                // Novo plano de assinatura
                $newSubscriptions[] = $subscription;
              } else {
                // Plano de assinatura existente
                $heldSubscriptions[] = $subscription['subscriptionplanid'];
                $updSubscriptions[]  = $subscription;
              }
            }
            
            // Recupera os planos de assinatura armazenados atualmente
            $subscriptions = SubscriptionPlan::where('planid',
                  $planID
                )
              ->get(['subscriptionplanid'])
              ->toArray()
            ;
            $oldSubscriptions = [ ];
            foreach ($subscriptions as $subscription) {
              $oldSubscriptions[] = $subscription['subscriptionplanid'];
            }

            // Verifica quais os planos de assinatura estavam na base de
            // dados e precisam ser removidos
            $delSubscriptions = array_diff($oldSubscriptions, $heldSubscriptions);
            
            // ================================[ Valores Cobrados ]=====
            // Recupera as informações dos valores cobrados e separa os
            // dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================
            
            // Analisa os valores cobrados informados, de forma a
            // separar quais valores precisam ser adicionados, removidos
            // e atualizados
            
            // Matrizes que armazenarão os dados dos valores cobrados a
            // serem adicionados, atualizados e removidos
            $newCharges = [ ];
            $updCharges = [ ];
            $delCharges = [ ];

            // Separa os itens que precisam ser adicionados, modificados
            // e removidos respectivamente
            foreach ($chargesData AS $charge) {
              if ($charge['added'] === 'true') {
                // O tipo de cobrança está selecionada
                if (empty($charge['planchargeid'])) {
                  // Adiciona o tipo de cobrança
                  $newCharges[] = $charge;
                } else {
                  // Atualiza o tipo de cobrança
                  $updCharges[]  = $charge;
                }
              } else {
                if (!empty($charge['planchargeid'])) {
                  // Remove o tipo de cobrança
                  $delCharges[] = $charge['planchargeid'];
                }
              }
            }

            // ========================[ Características técnicas ]=====
            // Recupera as informações das características técnicas e
            // separa os dados para as operações de inserção,
            // atualização e remoção.
            // =========================================================
            
            // Analisa as características técnicas informados, de forma
            // a separar quais valores precisam ser adicionados,
            // removidos e atualizados
            
            // Matrizes que armazenarão os dados das características
            // técnicas a serem adicionadas e removidas
            $newFeatures = [ ];
            $delFeatures = [ ];

            // Determina quais características técnicas serão mantidas
            // (e atualizadas) e as que precisam ser adicionadas (novas)
            foreach ($featuresData AS $feature) {
              if ($feature['added'] === 'true') {
                // A característica técnica está selecionada
                if (empty($feature['planfeatureid'])) {
                  // Adiciona a característica técnica
                  $newFeatures[] = $feature;
                }
              } else {
                if (!empty($feature['planfeatureid'])) {
                  // Remove a característica técnica
                  $delFeatures[] = $feature['planfeatureid'];
                }
              }
            }

            // ========================================[ Gravação ]=====

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // --------------------------------[ Plano de serviço ]-----
            // Grava as informações do plano
            $plan = Plan::findOrFail($planID);
            $plan->fill($planData);
            // Adiciona o usuário responsável pela modificação
            $plan->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $plan->save();
            
            // ----------------------------[ Planos de assinatura ]-----

            // Primeiro apagamos os planos de assinatura removidos pelo
            // usuário durante a edição
            foreach ($delSubscriptions as $subscriptionID) {
              // Apaga cada plano de assinatura
              $subscription = SubscriptionPlan::findOrFail($subscriptionID);
              $subscription->delete();
            }

            // Agora inserimos os novos planos de assinatura
            foreach ($newSubscriptions as $subscriptionData) {
              // Incluímos um novo plano de assinatura neste plano
              unset($subscriptionData['subscriptionplanid']);
              $subscription = new SubscriptionPlan();
              $subscription->fill($subscriptionData);
              $subscription->planid = $planID;
              $subscription->save();
            }

            // Por último, modificamos os planos de assinatura mantidos
            foreach($updSubscriptions AS $subscriptionData) {
              // Retira a ID da plano de assinatura
              $subscriptionID = $subscriptionData['subscriptionplanid'];
              unset($subscriptionData['subscriptionplanid']);
              
              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe
              unset($subscriptionData['planid']);
              
              // Grava as informações do plano de assinatura
              $subscription = SubscriptionPlan::findOrFail($subscriptionID);
              $subscription->fill($subscriptionData);
              $subscription->save();
            }
            
            // --------------------------------[ Valores cobrados ]-----

            // Primeiro apagamos os valores cobrados removidos pelo
            // usuário durante a edição
            foreach ($delCharges as $chargeID) {
              // Apaga cada valor cobrado
              $charge = PlanCharge::findOrFail($chargeID);
              $charge->delete();
            }

            // Agora inserimos os novos valores cobrados
            foreach ($newCharges as $chargeData) {
              // Incluímos um novo valor cobrado neste plano
              unset($chargeData['planchargeid']);
              $charge = new PlanCharge();
              $charge->fill($chargeData);
              $charge->planid = $planID;
              $charge->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->save();
            }

            // Por último, modificamos os valores cobrados mantidos
            foreach($updCharges AS $chargeData) {
              // Retira a ID do valor cobrado
              $chargeID = $chargeData['planchargeid'];
              unset($chargeData['planchargeid']);
              
              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe nem do contratante
              unset($chargeData['contractorid']);
              unset($chargeData['planid']);
              
              // Grava as informações do valor cobrado
              $charge = PlanCharge::findOrFail($chargeID);
              $charge->fill($chargeData);
              $charge->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->save();
            }
            
            // ------------------------[ Características técnicas ]-----

            // Primeiro apagamos as características técnicas removidas
            // pelo usuário durante a edição
            foreach ($delFeatures as $featureID) {
              // Apaga cada característica técnica
              $feature = PlanFeature::findOrFail($featureID);
              $feature->delete();
            }

            // Agora inserimos as novas características técnicas
            foreach ($newFeatures as $featureData) {
              // Incluímos uma nova característica técnica neste plano
              unset($featureData['planfeatureid']);
              $feature = new PlanFeature();
              $feature->fill($featureData);
              $feature->planid = $planID;
              $feature->save();
            }

            // ---------------------------------------------------------

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Modificado o plano '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $planData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O plano <i>'{name}'"
              . "</i> foi modificado com sucesso.",
              [ 'name'  => $planData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Financial\Plans' ]
            );
            
            // Redireciona para a página de gerenciamento de planos
            return $this->redirect($response,
              'ERP\Financial\Plans'
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do plano '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}",
            [ 'name'  => $planData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do plano. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do plano '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'name'  => $planData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do plano. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do plano são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($plan);
    }
    
    // Exibe um formulário para edição de um plano
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Planos',
      $this->path('ERP\Financial\Plans')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Financial\Plans\Edit', [
        'planID' => $planID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do plano de serviço '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $plan['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/plans/plan.twig',
      [ 'formMethod' => 'PUT',
        'indicators' => $indicators,
        'measureTypes' => $measureTypes,
        'billingTypes' => $billingTypes,
        'featuresTable' => $featuresTable ])
    ;
  }
  
  /**
   * Remove o plano.
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
  public function delete(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à remoção de plano de serviço.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $planID = $args['planID'];

    try
    {
      // Recupera as informações do plano
      $plan = Plan::where('contractorid',
            '=', $contractor->id
          )
        ->where('planid', '=', $planID)
        ->firstOrFail()
      ;
      
      // Agora apaga o plano

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Agora apaga o plano e os valores relacionados
      $plan->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O plano '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $plan->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o plano "
              . "{$plan->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o plano "
        . "código {planID} para remoção.",
        [ 'planID' => $planID ]
      );
      
      $message = "Não foi possível localizar o plano para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de contrato ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $planID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o plano. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de contrato {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $planID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o plano. Erro "
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

  /**
   * Alterna o estado da ativação de um plano de um
   * contratante.
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
  public function toggleActive(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de ativação do "
      . "plano."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $planID = $args['planID'];
    
    try
    {
      // Recupera as informações do plano
      $plan = Plan::where('contractorid',
            '=', $contractor->id
          )
        ->where('planid', '=', $planID)
        ->firstOrFail()
      ;
      
      // Alterna o estado da ativação do plano
      $action     = $plan->active
        ? "desativado"
        : "ativado"
      ;
      $plan->active = !$plan->active;

      // Adiciona o usuário responsável pela modificação
      $plan->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $plan->save();
      
      // Registra o sucesso
      $this->info("O plano '{name}' do contratante "
        . "'{contractor}' foi {action} com sucesso.",
        [ 'name' => $plan->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado da ativação do tipo de
      // contrato foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O plano {$plan->name} foi "
              . "{$action} com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o plano "
        . "código {planID} no contratante '{contractor}' para "
        . "alternar o estado da ativação.",
        [ 'planID' => $planID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar o plano para "
        . "alternar o estado da ativação."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do plano '{name}' no contratante '{contractor}'. "
        . "Erro interno no banco de dados: {error}.",
        [ 'name'  => $plan->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "plano. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do plano '{name}' no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'name'  => $plan->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "plano. Erro interno."
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
