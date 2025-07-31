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
 * O controlador do gerenciamento de contratos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\BillingType;
use App\Models\Contract;
use App\Models\ContractCharge;
use App\Models\DisplacementFee;
use App\Models\DueDay;
use App\Models\Entity;
use App\Models\Entity AS Contractor;
use App\Models\Feature;
use App\Models\GeographicCoordinate;
use App\Models\Installation;
use App\Models\MeasureType;
use App\Models\Plan;
use App\Models\PaymentCondition;
use App\Models\PlanCharge;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;

class ContractsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * As funções de formatação especiais
   */
  use FormatterTrait;

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
      'contractid' => V::notBlank()
        ->intVal()
        ->setName('ID do contrato'),
      'contractnumber' => V::notBlank()
        ->setName('Número do contrato'),
      'customername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do cliente'),
      'customerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 50)
        ->setName('Unidade/Filial'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial'),
      'planid' => V::notBlank()
        ->intVal()
        ->setName('Plano de serviços'),
      'subscriptionplanid' => V::notBlank()
        ->intVal()
        ->setName('Forma de assinatura do plano'),
      'signaturedate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data da assinatura do contrato'),
      'enddate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data do encerramento do contrato'),
      'monthprice' => V::numericValue()
        ->minimumValue('1,00')
        ->setName('Mensalidade'),
      'dateofnextreadjustment' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data do próximo reajuste'),
      'duedayid' => V::notBlank()
        ->intVal()
        ->setName('Dia de vencimento'),
      'paymentconditionid' => V::notBlank()
        ->intVal()
        ->setName('Forma de pagamento do contrato'),
      'additionalpaymentconditionid' => V::notBlank()
        ->intVal()
        ->setName('Forma de pagamento para valores adicionais'),
      'prepaid' => V::boolVal()
        ->setName('Contrato pré-pago'),
      'chargeanytariffs' => V::boolVal()
        ->setName('Cobrar tarifas definidas no meio de pagamento'),
      'unifybilling' => V::boolVal()
        ->setName('Unificar cobranças de instalações'),
      'starttermafterinstallation' => V::boolVal()
        ->setName('Início da vigência se dá após a instalação'),
      'notchargeloyaltybreak' => V::boolVal()
        ->setName('Não cobrar multa por quebra de fidelidade'),
      'manualreadjustment' => V::boolVal()
        ->setName('Reajuste manual'),
      'active' => V::boolVal()
        ->setName('Plano de serviço ativo'),
      'charges' => [
        'added' => V::boolVal()
          ->setName('Tipo de tarifa adicionado'),
        'planchargeid' => V::intVal()
          ->setName('ID do tipo de tarifa cobrada'),
        'planchargevalue' => V::numericValue()
          ->setName('Valor cobrado'),
        'contractchargeid' => V::intVal()
          ->setName('ID do tipo de tarifa cobrada'),
        'billingtypeid' => V::intVal()
          ->min(1)
          ->setName('Tipo de cobrança'),
        'chargevalue' => V::numericValue()
          ->setName('Valor cobrado')
      ],
      'maxwaitingtime' => V::intVal()
        ->setName('Tempo máximo'),
      'unproductivevisittype' => V::intVal()
        ->setName('Valor cobrado por visita improdutiva'),
      'unproductivevisit' => V::numericValue()
        ->setName('Tipo do valor cobrado por visita improdutiva'),
      'minimumtime' => V::intVal()
        ->setName('Tempo mínimo'),
      'minimumtimetype' => V::intVal()
        ->setName('Tipo do tempo mínimo'),
      'frustratedvisittype' => V::intVal()
        ->setName('Valor cobrado por visita frustrada'),
      'frustratedvisit' => V::numericValue()
        ->setName('Tipo do valor cobrado por visita frustrada'),
      'displacements' => [
        'displacementfeeid' => V::intVal()
          ->setName('ID da faixa de cobrança'),
        'distance' => V::notBlank()
          ->intVal()
          ->setName('Distância'),
        'value' => V::numericValue()
          ->setName('Valor cobrado'),
      ],
      'geographiccoordinateid' => V::intVal()
        ->setName('Ponto de referência para cálculo'),
      'referencename' => V::optional(
            V::notBlank()
              ->length(2, 50)
          )
        ->setName('Nome da referência'),
      'latitude' => V::optional(
            V::numericValue()
          )
        ->setName('Latitude'),
      'longitude' => V::optional(
            V::numericValue()
          )
        ->setName('Longitude'),
      'features' => [
        'contractfeatureid' => V::intVal()
          ->setName('ID da característica técnica do contrato'),
        'featureid' => V::notBlank()
          ->intVal()
          ->setName('ID da característica técnica')
      ]
    ];

    if ($addition) {
      // Ajusta as regras para adição de um novo contrato
      
      // Retiramos as regras para campos que não fazem parte desta parte
      // da edição
      unset($validationRules['contractid']);
      unset($validationRules['contractnumber']);
      unset($validationRules['enddate']);
      unset($validationRules['effectivepricedate']);
      unset($validationRules['dateofnextreadjustment']);
      unset($validationRules['active']);
      unset($validationRules['charges']);
      unset($validationRules['features']);
      unset($validationRules['maxwaitingtime']);
      unset($validationRules['unproductivevisittype']);
      unset($validationRules['unproductivevisit']);
      unset($validationRules['minimumtime']);
      unset($validationRules['minimumtimetype']);
      unset($validationRules['frustratedvisittype']);
      unset($validationRules['frustratedvisit']);
      unset($validationRules['geographiccoordinateid']);

      // Acrescentamos campos complementares
      $validationRules['realmonthprice'] = V::numericValue()
        ->minimumValue('0,00')
        ->setName('Valor com desconto')
      ;
      $validationRules['discount'] = V::optional(
            V::numericValue()
               ->minimumValue('0,00')
          )
        ->setName('Desconto')
      ;
      $validationRules['amountOfInstallations'] = V::notBlank()
        ->intVal()
        ->setName('Quantidade contratada')
      ;
      $validationRules['blockcustomer'] = V::optional(
            V::boolVal()
          )
        ->setName('Bloquear cliente')
      ;
    } else {
      // Ajusta as regras para edição de um contrato
      $validationRules['regionaldocumenttypename'] = V::notEmpty()
        ->setName('Tipo do documento');
      $validationRules['regionaldocumentnumber'] = V::optional(
            V::notEmpty()
          )
        ->setName('Número do documento');
      $validationRules['regionaldocumentstate'] = V::optional(
            V::notEmpty()
          )
        ->setName('UF do documento');
      $validationRules['nationalregister'] = V::notEmpty()
        ->setName('CPF/CNPJ');
      $validationRules['informPayingCustomer'] = V::boolVal()
        ->setName('Informar dados do pagante');
      $validationRules['customerpayername'] = V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome do cliente responsável pelo pagamento');
      $validationRules['customerpayerid'] = V::optional(
            V::notBlank()
              ->intVal()
          )
        ->setName('ID do cliente pagante');
      $validationRules['subsidiarypayername'] = V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Unidade/filial/titular responsável pelo pagamento');
      $validationRules['subsidiarypayerid'] = V::optional(
            V::notBlank()
              ->intVal()
          )
        ->setName('ID da unidade/filial/titular');
      $validationRules['planname'] = V::notEmpty()
        ->setName('Nome do plano');
      $validationRules['subscriptionplanname'] = V::notEmpty()
        ->setName('Nome da forma de assinatura do plano');
      $validationRules['planmonthprice'] = V::notEmpty()
        ->setName('Valor do plano');
      $validationRules['discountrate'] = V::notEmpty()
        ->setName('Desconto oferecido');
      $validationRules['duration'] = V::notEmpty()
        ->setName('Duração do contrato');
      $validationRules['loyaltyperiod'] = V::notEmpty()
        ->setName('Período de fidelidade');
      $validationRules['readjustmentperiod'] = V::notEmpty()
        ->setName('Período de reajuste');
      $validationRules['indicatorname'] = V::notEmpty()
        ->setName('Indicador financeiro usado no reajuste');
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de coordenadas geográficas.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter as coordenadas
   *   geográficas disponíveis
   *
   * @return Collection
   *   A matriz com as informações de coordenadas geográficas
   *
   * @throws RuntimeException
   *   Em caso de não termos coordenadas geográficas definidas
   */
  protected function getGeographicCoordinates(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de planos deste contratante
      $geographicCoordinates = GeographicCoordinate::where("contractorid", '=', $contractorID)
        ->get([
            'geographiccoordinateid as id',
            'name',
            $this->DB->raw("location[0] AS latitude"),
            $this->DB->raw("location[1] AS longitude")
          ])
      ;

      if ( $geographicCoordinates->isEmpty() ) {
        throw new Exception("Não temos nenhuma coordenada geográfica "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "coordenadas geográficas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as "
        . "coordenadas geográficas"
      );
    }

    return $geographicCoordinates;
  }

  /**
   * Recupera as informações da coordenada geográfica padrão para o
   * contratante.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter a coordenada
   *   geográfica padrão
   *
   * @return int
   *   O ID da coordenada geográfica
   *
   * @throws RuntimeException
   *   Em caso de erro
   */
  protected function getDefaultCoordinateID(
    int $contractorID
  ): int
  {
    try {
      // Recupera as informações de planos deste contratante
      $defaultCoordinateID = Contractor::where("entityid", '=', $contractorID)
        ->get([
            'defaultcoordinateid as id'
          ])
        ->first()
        ->id
      ;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "coordenadas geográficas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter a coordenada "
        . "geográfica padrão"
      );
    }

    return $defaultCoordinateID;
  }

  /**
   * Recupera as informações de planos de serviços.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os planos
   *   disponíveis
   *
   * @return Collection
   *   A matriz com as informações de planos de serviços
   *
   * @throws RuntimeException
   *   Em caso de não termos planos de serviços
   */
  protected function getPlans(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de planos deste contratante
      $plans = Plan::where("contractorid", '=', $contractorID)
        ->get([
            'planid as id',
            'name',
            'monthprice',
            'active'
          ])
      ;

      if ( $plans->isEmpty() ) {
        throw new Exception("Não temos nenhum plano de serviços "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de planos de "
        . "serviços. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os planos de "
        . "serviços"
      );
    }

    return $plans;
  }

  /**
   * Recupera as informações de planos de assinatura.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os planos de
   *   assinatura disponíveis
   *
   * @return array
   *   A matriz com as informações de planos de assinatura
   *
   * @throws RuntimeException
   *   Em caso de não termos planos de assinatura
   */
  protected function getSubscriptionPlans(
    int $contractorID
  ): array
  {
    try {
      // Recupera as informações de planos deste contratante
      $subscriptionPlans = SubscriptionPlan::join('plans',
            'subscriptionplans.planid', '=', 'plans.planid')
        ->where("plans.contractorid", '=', $contractorID)
        ->orderBy('subscriptionplans.planid')
        ->orderBy('subscriptionplans.numberofmonths')
        ->get([
            'subscriptionplans.subscriptionplanid as id',
            'subscriptionplans.planid',
            'subscriptionplans.numberofmonths',
            'subscriptionplans.discountrate'
          ])
      ;

      if ( $subscriptionPlans->isEmpty() ) {
        throw new Exception("Não temos nenhum plano de assinatura "
          . "cadastrado"
        );
      }

      $offeredPlans = [];
      foreach ($subscriptionPlans AS $subscriptionPlan) {
        // Criamos o próximo plano de assinatura oferecido
        $newSubscriptionPlan = [
          'name' => (($subscriptionPlan->numberofmonths > 1)
            ? "Assinado por {$subscriptionPlan->numberofmonths} meses"
            : "Pagamentos mensais"
          ),
          'value' => $subscriptionPlan->id,
          'months' => $subscriptionPlan->numberofmonths,
          'discount' => $subscriptionPlan->discountrate
        ];

        if (isset($offeredPlans[$subscriptionPlan->planid])) {
          $offeredPlans[$subscriptionPlan->planid][] = 
            $newSubscriptionPlan
          ;
        } else {
          $offeredPlans[$subscriptionPlan->planid] = [
            $newSubscriptionPlan
          ];
        }
      }

      $subscriptionPlans = $offeredPlans;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de planos de "
        . "assinatura. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os planos de "
        . "serviços"
      );
    }

    return $subscriptionPlans;
  }

  /**
   * Recupera as informações de dias de vencimento.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os dias de
   *   vencimento disponíveis
   *
   * @return Collection
   *   A matriz com as informações de dias de vencimento
   *
   * @throws RuntimeException
   *   Em caso de não termos dias de vencimento definidos
   */
  protected function getDueDays(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de dias de vencimento deste contratante
      $dueDays = DueDay::where("contractorid", '=', $contractorID)
        ->get([
            'duedayid as id',
            'day'
          ])
      ;

      if ( $dueDays->isEmpty() ) {
        throw new Exception("Não temos nenhum dia de vencimento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de dias de "
        . "vencimento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os dias de "
        . "vencimento"
      );
    }

    return $dueDays;
  }

  /**
   * Recupera as informações de condições de pagamento.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter as condições de
   *   pagamento disponíveis
   *
   * @return Collection
   *   A matriz com as informações de condições de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos condições de pagamento
   */
  protected function getPaymentConditions(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de condições de pagamento deste
      // contratante
      $paymentConditions = PaymentCondition::where("contractorid", '=', $contractorID)
        ->where("blocked", "false")
        ->get([
            'paymentconditionid as id',
            'name'
          ])
      ;

      if ( $paymentConditions->isEmpty() ) {
        throw new Exception("Não temos nenhuma condição de pagamento "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de condições "
        . "de pagamento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as condições "
        . "de pagamento"
      );
    }

    return $paymentConditions;
  }

  /**
   * Recupera as informações de tipos de cobranças junto com as
   * informações daquelas que são cobradas no plano.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os tipos de
   *   cobrança disponíveis
   * @param int $planID
   *   A ID do plano
   *
   * @return Collection
   *   A matriz com as informações de tipos de cobranças bem como
   *   daquelas que estão presentes no plano informado
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de cobranças
   */
  protected function getBillingTypes(
    int $contractorID,
    int $planID
  ): Collection
  {
    try {
      // Recupera as informações de tipos de cobranças deste contratante
      $billingTypes = BillingType::leftJoin('installmenttypes', 'billingtypes.installmenttypeid',
            '=', 'installmenttypes.installmenttypeid'
          )
        ->leftJoin('plancharges', function($join) use ($planID)
          {
            $join->on('billingtypes.billingtypeid',
              '=', 'plancharges.billingtypeid');
            $join->on("plancharges.planid",
              '=',$this->DB->raw($planID));
          })
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
              . "  WHEN plancharges.planchargeid IS NULL THEN 0"
              . "  ELSE plancharges.planchargeid "
              . "END AS planchargeid"),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN plancharges.planchargeid IS NULL THEN 0.00"
              . "  ELSE plancharges.chargevalue "
              . "END AS planchargevalue"),
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
   * Recupera as informações de características técnicas.
   *
   * @return Collection
   *   A matriz com as informações de características técnicas
   *
   * @throws RuntimeException
   *   Em caso de não termos características técnicas
   */
  protected function getFeatures(): Collection
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

    return $features;
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
   * Recupera as informações de unidades de tempo.
   *
   * @return array
   *   A matriz com as informações de unidades de tempo
   */
  protected function getTimeTypes(): array
  {
    return [
      [
        'id' => 1,
        'name' => 'hora(s)'
      ],
      [
        'id' => 2,
        'name' => 'dia(s)'
      ]
    ];
  }

  /**
   * Exibe a página inicial do gerenciamento de contratos.
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
    $this->breadcrumb->push('Contratos',
      $this->path('ERP\Financial\Contracts')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de contratos.");
    
    // Recupera os dados da sessão
    $contract = $this->session->get('contract',
      [ 'searchField' => 'plate',
        'searchValue' => '',
        'customer' => [
          'id' => 0,
          'name' => '',
          'subsidiaryID' => 0
        ],
        'displayStart' => 0
      ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/contracts/contracts.twig',
      [ 'contract' => $contract ])
    ;
  }

  /**
   * Recupera a relação dos contratos em formato JSON.
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
    $this->debug("Acesso à relação de contratos.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = (array) $request->getParsedBody();

    if (isset($postParams['request'])) {
      // Lida com uma requisição de um dropdown
      $customerID    = $postParams['customerID'];
      $subsidiaryID  = $postParams['subsidiaryID'];
      $includeSuspended = false;
      if (isset($postParams['includeSuspended'])) {
        $includeSuspended = $postParams['includeSuspended']==='true'
          ? true
          : false
        ;
      }
      $includeFinish = false;
      if (isset($postParams['includeFinish'])) {
        $includeFinish = $postParams['includeFinish']==='true'
          ? true
          : false
        ;
      }

      $complement = '';
      if ($includeSuspended) {
        $complement = '(mesmo que esteja suspenso)';
      } else {
        $complement = '(apenas se estiver ativo)';
      }
      if ($includeFinish) {
        $complement = ' incluindo contratos encerrados';
      }

      $this->debug("Requisitando os contratos do cliente {customerID} "
        . "na sua unidade/filial {subsidiaryID} {complement}",
        [ 'customerID' => $customerID,
          'subsidiaryID' => $subsidiaryID,
          'complement' => $complement ]
      );

      $contractsQry = Contract::where('customerid', '=', $customerID)
        ->where('subsidiaryid', '=', $subsidiaryID)
      ;
      
      if ($includeFinish === false) {
        $contractsQry
          ->whereNull('contracts.enddate')
        ;
      }
      if ($includeSuspended === false) {
        $contractsQry
          ->where('contracts.active', 'true')
        ;
      }

      $contracts = $contractsQry
        ->orderBy('contractid')
        ->get([
            'contractid AS id',
            $this->DB->raw('getContractNumber(createdat) AS contractnumber'),
            $this->DB->raw("trim(to_char(contracts.monthprice, '9999999999D99')) AS monthprice"),
            $this->DB->raw(''
              . "CASE"
              . "  WHEN signaturedate IS NULL THEN 'Não assinado'"
              . "  ELSE 'Assinado em ' || to_char(signaturedate, 'DD/MM/YYYY') "
              . "END AS signature"),
            $this->DB->raw(''
              . "CASE"
              . "  WHEN enddate IS NOT NULL THEN 'Encerrado em ' || to_char(enddate, 'DD/MM/YYYY')"
              . "  WHEN active = FALSE THEN 'Contrato suspenso'"
              . "  ELSE 'Ativo'"
              . "END AS termination")
          ])
      ;
      if ( $contracts->isEmpty() ) {
        $results = [];
      } else {
        $results = [];
        foreach ($contracts AS $contract) {
          $results[] = [
            'name' => "{$contract->contractnumber}",
            'value' => $contract->id,
            'signature' => $contract->signature,
            'termination' => $contract->termination,
            'monthprice' => $contract->monthprice,
            'description' => ""
              . "<div class=\"hidden\">"
              .   "{$contract->signature}<br>"
              . (($contract->termination === 'Ativo')
                  ? ''
                  : '<span style="color:darkred;">'
                    . $contract->termination
                    . "</span><br>"
                )
              . "</div>"
              . "<span class=\"symbol\">(R$ <span class=\"monetary\">{$contract->monthprice}</span>)</span>",
            'descriptionVertical' => true
          ];
        }
      }

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'success' => true,
            'results' => $results
          ])
      ;
    }
    
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
    $searchField  = $postParams['searchField'];
    $searchValue  = trim($postParams['searchValue']);
    $customerID   = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('contract',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'customer' => [
          'id' => $customerID,
          'name' => $customerName,
          'subsidiaryID' => $subsidiaryID
        ],
        'displayStart' => $start
      ]
    );
    $active = 'NULL';

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido dos campos de pesquisa
      $customerID = $customerID?$customerID:0;

      // Realiza a consulta
      $contractorID = $this->authorization->getContractor()->id;
      $sql = "SELECT C.contractID AS id,
                     C.contractorID,
                     C.contractorName,
                     C.contractorBlocked,
                     C.customerID,
                     C.customerName,
                     C.customerBlocked,
                     C.customerTypeID,
                     C.customerTypeName,
                     C.cooperative,
                     C.juridicalperson,
                     C.subsidiaryID,
                     C.subsidiaryName,
                     C.subsidiaryBlocked,
                     C.contractNumber,
                     C.planID,
                     C.planName,
                     C.dueDay,
                     to_char(C.signatureDate, 'DD/MM/YYYY') AS signatureDate,
                     CASE
                       WHEN C.contractendDate IS NULL THEN NULL
                       ELSE to_char(C.contractendDate, 'DD/MM/YYYY')
                     END AS contractendDate,
                     C.paymentConditionID,
                     C.paymentConditionName,
                     C.contractPrice,
                     C.contractActive,
                     C.installationID,
                     C.installationNumber,
                     C.noTracker,
                     C.containsTrackingData,
                     C.monthPrice,
                     CASE
                       WHEN C.startDate IS NULL THEN NULL
                       ELSE to_char(C.startDate, 'DD/MM/YYYY')
                     END AS startDate,
                     CASE
                       WHEN C.endDate IS NULL THEN NULL
                       ELSE to_char(C.endDate, 'DD/MM/YYYY')
                     END AS endDate,
                     CASE
                       WHEN C.dateOfNextReadjustment IS NULL THEN NULL
                       ELSE to_char(C.dateOfNextReadjustment, 'DD/MM/YYYY')
                     END AS dateOfNextReadjustment,
                     CASE
                       WHEN C.lastDayOfCalculatedPeriod IS NULL THEN NULL
                       ELSE to_char(C.lastDayOfCalculatedPeriod, 'DD/MM/YYYY')
                     END AS lastDayOfCalculatedPeriod,
                     CASE
                       WHEN C.lastDayOfBillingPeriod IS NULL THEN NULL
                       ELSE to_char(C.lastDayOfBillingPeriod, 'DD/MM/YYYY')
                     END AS lastDayOfBillingPeriod,
                     C.plate,
                     C.vehicleTypeName,
                     C.vehicleBrandName,
                     C.vehicleModelName,
                     C.vehicleColorName,
                     C.vehicleBlocked,
                     C.blockedlevel,
                     C.fullcount
                FROM erp.getContractsData({$contractorID}, {$customerID},
                  {$subsidiaryID}, 0, '{$searchValue}', '{$searchField}',
                  {$active}, FALSE, FALSE, '{$ORDER}', {$start}, {$length}) AS C;"
      ;
      $contracts = $this->DB->select($sql);

      if (count($contracts) > 0) {
        $rowCount = $contracts[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $contracts
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos contratos cadastrados.";
        } else {
          switch ($searchField) {
            case 'contractNumber':
              $error = "Não temos contratos cadastrados cujo número do "
                . "contrato contém <i>{$searchValue}</i>."
              ;
              
              break;
            default:
              $error = "Não temos contratos cadastrados que possuam "
                . "uma instalação cujo número contém "
                . "<i>{$searchValue}</i>."
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
        [ 'module' => 'contratos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'contratos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratos. Erro interno."
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
   * Exibe um formulário para adição de um contrato, quando solicitado,
   * e confirma os dados enviados.
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
  public function add(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações dos planos de serviços
      $plans = $this->getPlans($contractor->id);

      // Recupera as informações dos planos de assinaturas
      $subscriptionPlans = $this->getSubscriptionPlans($contractor->id);

      // Recupera as informações de dias de vencimento
      $dueDays = $this->getDueDays($contractor->id);

      // Recupera as informações de condições de pagamento
      $paymentConditions = $this->getPaymentConditions($contractor->id);

      // Recupera as informações da coordenada geográfica padrão
      $defaultCoordinateID = $this->getDefaultCoordinateID($contractor->id);

      if (array_key_exists('customerID', $args)) {
        // Recupera as informações do cliente
        $customerID = $args['customerID'];
      } else {
        $customerID = 0;
      }
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Contracts' ]
      );

      // Redireciona para a página de gerenciamento de contratos
      return $this->redirect($response, 'ERP\Financial\Contracts');
    }

    if ($customerID > 0) {
      try {
        $customer = Entity::join('subsidiaries',
              'entities.entityid', '=', 'subsidiaries.entityid'
            )
          ->join("entitiestypes", "entities.entitytypeid",
              '=', "entitiestypes.entitytypeid"
            )
          ->join('documenttypes', 'subsidiaries.regionaldocumenttype',
              '=', 'documenttypes.documenttypeid'
            )
          ->where('entities.contractorid', '=', $contractor->id)
          ->where('entities.entityid', '=', $customerID)
          ->orderBy('subsidiaries.subsidiaryid')
          ->get([
              'entities.entityid AS customerid',
              'entities.name AS customername',
              'entitiestypes.juridicalperson as juridicalperson',
              'subsidiaries.subsidiaryid',
              'subsidiaries.name AS subsidiaryname',
              'documenttypes.name AS regionaldocumenttypename',
              'subsidiaries.regionaldocumentnumber',
              'subsidiaries.regionaldocumentstate',
              'subsidiaries.nationalregister',
            ])
        ;

        if ( $customer->isEmpty() ) {
          throw new ModelNotFoundException("Não temos nenhum cliente com "
            . "o código {$customerID} cadastrado"
          );
        }
        $customer = $customer
          ->first()
          ->toArray()
        ;
      }
      catch(ModelNotFoundException $exception)
      {
        // Registra o erro
        $this->error("Não foi possível localizar o cliente código "
          . "{customerID}.",
          [ 'customerID' => $customerID ]
        );
        
        // Alerta o usuário
        $this->flash("error", "Não foi possível localizar este cliente."
        );
        
        // Registra o evento
        $this->debug("Redirecionando para {routeName}",
          [ 'routeName' => 'ERP\Cadastre\Customers' ]
        );
        
        // Redireciona para a página de gerenciamento de clientes
        return $this->redirect($response,
          'ERP\Cadastre\Customers'
        );
      }
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de contrato.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do contrato são VÁLIDOS');

        // Recupera os dados do contrato
        $contractData = $this->validator->getValues();

        try
        {
          // Grava o novo contrato
          
          // Precisa retirar dos parâmetros as informações
          // correspondentes às outras tabelas
          $planID = $contractData['planid'];
          $amountOfInstallations = $contractData['amountOfInstallations'];
          unset($contractData['amountOfInstallations']);

          // Acrescentamos que o contrato está ativo
          $contractData['active'] = 'true';
          
          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Criamos o novo contrato
          $contract = new Contract();
          $contract->fill($contractData);
          // Informa que o contrato está ativo
          $contract->active = true;
          // Informa a coordenada geográfica de referência padrão para
          // cálculo de deslocamento do técnico como sendo o padrão do
          // cliente
          $contract->geographiccoordinateid = $defaultCoordinateID;
          // Adiciona o contratante e usuários atuais
          $contract->contractorid = $contractor->id;
          $contract->createdbyuserid =
            $this->authorization->getUser()->userid
          ;
          $contract->updatedbyuserid =
            $this->authorization->getUser()->userid
          ;
          $contract->save();
          $contractID = $contract->contractid;
          
          // Incluímos todos os valores cobrados no plano também neste
          // contrato, de forma que possam depois serem modificados
          $chargesData = PlanCharge::where('planid', $planID)
            ->orderBy('plancharges.planchargeid')
            ->get()
          ;

          foreach($chargesData AS $chargeData) {
            // Incluímos um novo valor cobrado deste contrato
            $charge = new ContractCharge();
            $charge->contractid      = $contractID;
            $charge->billingtypeid   = $chargeData['billingtypeid'];
            $charge->chargevalue = floatval(
              str_replace(',', '.',
                str_replace('.', '', $chargeData['chargevalue'])
              )
            );
            $charge->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $charge->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $charge->save();
          }

          // Incluímos uma faixa de cobrança de deslocamento sem valores
          // cobrados
          $displacement = new DisplacementFee();
          $displacement->contractid = $contractID;
          $displacement->distance = null;
          $displacement->value = 0.00;
          $displacement->save();

          // Incluímos a quantidade de instalações determinadas
          for ($count=0; $count < $amountOfInstallations; $count++) { 
            // Incluímos uma nova instalação
            $installation = new Installation();
            $installation->contractorid       = $contractor->id;
            $installation->customerid         = $contractData['customerid'];
            $installation->subsidiaryid       = $contractData['subsidiaryid'];
            $installation->contractid         = $contractID;
            $installation->planid             = $contractData['planid'];
            $installation->subscriptionplanid = $contractData['subscriptionplanid'];
            $installation->monthprice         = $contractData['monthprice'];
            $installation->monthprice = floatval(
              str_replace(',', '.',
                str_replace('.', '', $contractData['monthprice'])
              )
            );
            $installation->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $installation->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $installation->save();
          }

          // Atualizamos o número da instalação em todas as instalações
          // deste contrato
          $sql = ""
            . "UPDATE erp.installations
                  SET installationnumber = erp.generateInstallationNumber(contractorID, contractID, installationID)
                WHERE installations.contractID = {$contractID};"
          ;
          $this->DB->select($sql);

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Cadastrado o contrato do cliente "
            . "'{customerName}' no contratante '{contractor}' com "
            . "sucesso.",
            [ 'customerName' => $contractData['customername'],
              'contractor' => $contractor->name ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O contrato do cliente "
            . "<i>'{customerName}'</i>foi cadastrado com sucesso.",
            [ 'customerName'  => $contractData['customername'] ]
          );
          
          if ($customerID > 0) {
            // Redireciona para a página de gerenciamento de clientes
            $routeName = 'ERP\Cadastre\Customers';
          } else {
            // Redireciona para a página de gerenciamento de contratos
            $routeName = 'ERP\Financial\Contracts';
          }

          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => $routeName ]
          );
          
          // Redireciona para a página
          return $this->redirect($response,
            $routeName)
          ;
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "contrato do cliente '{customerName}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'customerName'  => $contractData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do contrato. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "contrato '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'customerName'  => $contractData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do contrato. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do contrato são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyContract = [
        //'planid' => $plans[0]['id'],
        //'subscriptionplanid' => $subscriptionPlans[$plans[0]['id']][0]['value'],
        'planid' => 0,
        'subscriptionplanid' => 0,
        'monthprice' => '0,00',
        'realmonthprice' => '0,00',
        'discount' => '0.00',
        'effectivepricedate' => Carbon::now()->format('d/m/Y'),
        'duedayid' => $dueDays[0]['duedayid'],
        'amountOfInstallations' => 1,
        'vehicles' => [[
          'vehicleid' => 0,
          'plate' => '',
          'brand' => '',
          'model' => ''
        ]],
        // Estou selecionando manualmente aqui para facilitar o cadastro
        // inicial, mas precisamos mudar para algo no plano
        'paymentconditionid' => 3,
        'additionalpaymentconditionid' => 3,
        'prepaid' => "false",
        'chargeanytariffs' => "true",
        'unifybilling' => "true",
        'starttermafterinstallation' => "true",
        'manualreadjustment' => "false"
      ];

      if ($customerID > 0) {
        // Adicionamos as informações do cliente
        $emptyContract = array_merge($emptyContract, $customer);
        $emptyContract['blockcustomer'] = "true";
      } else {
        $emptyContract['blockcustomer'] = "false";
      }
      $this->validator->setValues($emptyContract);
    }
    
    // Exibe um formulário para adição de um contrato
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Contratos',
      $this->path('ERP\Financial\Contracts')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Financial\Contracts\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de contrato no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/contracts/newcontract.twig',
      [
        'formMethod' => 'POST',
        'plans' => $plans,
        'subscriptionPlans' => $subscriptionPlans,
        'dueDays' => $dueDays,
        'paymentConditions' => $paymentConditions
      ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um contrato, quando
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

      // Recupera as informações de dias de vencimento
      $dueDays = $this->getDueDays($contractor->id);

      // Recupera as informações de condições de pagamento
      $paymentConditions = $this->getPaymentConditions($contractor->id);

      // Recupera as informações de coordenadas geográficas
      $geographicCoordinates = $this->getGeographicCoordinates(
        $contractor->id
      );

      // Recupera as informações de unidades de medidas
      $measureTypes = $this->getMeasureTypes();

      // Recupera as informações de unidades de tempo
      $timeTypes = $this->getTimeTypes();

      // Recupera as informações das características técnicas
      //$technicalFeatures = $this->getFeatures();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Contracts' ]
      );

      // Redireciona para a página de gerenciamento de contratos
      return $this->redirect($response, 'ERP\Financial\Contracts');
    }

    try
    {
      // Recupera as informações do contrato
      $contractID = $args['contractID'];
      $contract = Contract::join('entities AS customers',
            'contracts.customerid', '=', 'customers.entityid'
          )
        ->join('subsidiaries',
            'contracts.subsidiaryid', '=', 'subsidiaries.subsidiaryid'
          )
        ->join("entitiestypes", "customers.entitytypeid",
            '=', "entitiestypes.entitytypeid"
          )
        ->join('documenttypes', 'subsidiaries.regionaldocumenttype',
            '=', 'documenttypes.documenttypeid'
          )
        ->join('entities AS customerpayer',
            'contracts.customerpayerid', '=', 'customerpayer.entityid'
          )
        ->join('subsidiaries AS subsidiaryofpayer',
            'contracts.subsidiarypayerid', '=', 'subsidiaryofpayer.subsidiaryid'
          )
        ->join("entitiestypes AS entitytypeofpayer", "customerpayer.entitytypeid",
            '=', "entitytypeofpayer.entitytypeid"
          )
        ->join('plans', 'contracts.planid',
            '=', 'plans.planid'
          )
        ->join('subscriptionplans', 'contracts.subscriptionplanid',
            '=', 'subscriptionplans.subscriptionplanid'
          )
        ->join('indicators', 'plans.indicatorid',
            '=', 'indicators.indicatorid'
          )
        ->join('geographiccoordinates', 'contracts.geographiccoordinateid',
            '=', 'geographiccoordinates.geographiccoordinateid'
          )
        ->join('users AS createduser',
            'contracts.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'contracts.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('contracts.contractorid', '=', $contractor->id)
        ->where('contracts.contractid', '=', $contractID)
        ->get([
            'contracts.*',
            $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
            'customers.name AS customername',
            'entitiestypes.juridicalperson as juridicalperson',
            'subsidiaries.name AS subsidiaryname',
            'documenttypes.name AS regionaldocumenttypename',
            'subsidiaries.regionaldocumentnumber',
            'subsidiaries.regionaldocumentstate',
            'subsidiaries.nationalregister',
            'customerpayer.name AS customerpayername',
            'entitytypeofpayer.juridicalperson as payerisjuridicalperson',
            'subsidiaryofpayer.name AS subsidiarypayername',
            'plans.name AS planname',
            'plans.monthprice AS planmonthprice',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN subscriptionplans.numberofmonths > 1 THEN 'Assinado por ' || subscriptionplans.numberofmonths || ' meses' "
              . "  ELSE 'Pagamentos mensais' "
              . "END AS subscriptionplanname"
            ),
            $this->DB->raw("trim(to_char(contracts.monthprice - (contracts.monthprice*subscriptionplans.discountrate/100), '9999999999D99')) AS realmonthprice"),
            'subscriptionplans.discountrate',
            'plans.duration',
            'plans.indicatorid',
            'indicators.name AS indicatorname',
            'plans.readjustmentperiod',
            'plans.loyaltyperiod',
            'geographiccoordinates.name AS geographiccoordinatename',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $contract->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum contrato com "
          . "o código {$contractID} cadastrado"
        );
      }
      $contract = $contract
        ->first()
        ->toArray()
      ;

      // Recupera as informações dos tipos de cobranças junto com as
      // informações das cobranças do plano
      $billingTypes = $this->getBillingTypes(
        $contractor->id, $contract['planid']
      );

      // Obtemos se temos itens de contrato que estejam ativos
      $installations = Installation::join('equipments',
            'installations.installationid', '=', 'equipments.installationid'
          )
        ->where('installations.contractid', '=', $contractID)
        ->count()
      ;

      // Acrescentamos a informação da seleção do pagante
      $contract['informPayingCustomer'] =
        ( ($contract['customerid'] !== $contract['customerpayerid']) ||
           ($contract['subsidiaryid'] !== $contract['subsidiarypayerid']) )
      ;

      // Recupera as informações dos valores cobrados
      // Agora recupera as informações dos valores cobrados
      $charges = ContractCharge::join('billingtypes',
            'contractcharges.billingtypeid', '=',
            'billingtypes.billingtypeid'
          )
        ->where('contractcharges.contractid', $contractID)
        ->get([
          'contractcharges.*',
          'billingtypes.name AS billingtypename'
        ])
      ;

      // Precisamos organizar os tipos de cobrança pelo código do tipo
      // de forma a permitir renderizar corretamente na página
      $contract['charges'] = [ ];
      if ( !$charges->isEmpty() ) {
        foreach ($charges->toArray() as $charge) {
          $charge['added'] = 'true';
          $contract['charges'][$charge['billingtypeid']] = $charge;
        }
      }

      // Recupera as informações das características técnicas disponíveis
      // TODO: Adaptar como feito no plano
      // $features = PlanFeature::join('features', 'features.featureid',
      //       '=', 'planfeatures.featureid'
      //     )
      //   ->where('planfeatures.planid', $planID)
      //   ->orderBy('features.name')
      //   ->get([
      //     'planfeatures.planfeatureid',
      //     'planfeatures.featureid',
      //     'features.name AS featurename'
      //   ])
      // ;

      // Precisamos organizar as características técnicas pelo código da
      // característica de forma a permitir renderizar corretamente na
      // página
      $plan['features'] = [ ];
      // if ( !$features->isEmpty() ) {
      //   foreach ($features->toArray() as $feature) {
      //     $feature['added'] = 'true';
      //     $plan['features'][$feature['featureid']] = $feature;
      //   }
      // }

      // Agora recupera as informações de faixas de cobranças do
      // deslocamento de técnicos para atendimento
      $contract['displacements'] = DisplacementFee::where(
            'contractid', $contractID
          )
        ->orderByRaw('distance NULLS FIRST')
        ->get()
        ->toArray()
      ;
      $contract['displacements'][0]['distance'] = 999999;

      // Sempre adiciona uma latitude e longitude zeradas
      $contract['latitude'] = '0,0000000';
      $contract['longitude'] = '0,0000000';
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o contrato "
        . "código {contractID}.",
        [ 'contractID' => $contractID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "contrato."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Contracts' ]
      );
      
      // Redireciona para a página de gerenciamento de contratos
      return $this->redirect($response,
        'ERP\Financial\Contracts'
      );
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do contrato '{number}' do "
        . "cliente '{customername}' no contratante {contractor}.",
        [ 'number' => $contract['contractnumber'],
          'customername' => $contract['customername'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do contrato são VÁLIDOS');

        // Grava as informações no banco de dados

        // Recupera os dados modificados do contrato
        $contractData = $this->validator->getValues();

        try
        {
          // Inicia parâmetros de análise
          $save = true;
          $changeNextReadjustment = false;

          // Verifica as informações de data para garantir que elas não
          // estejam invertidas ou incompletas
          if ($contractData['signaturedate']) {
            $today = Carbon::now();
            $signatureDate = Carbon::createFromFormat('d/m/Y',
              $contractData['signaturedate']
            );

            if ($signatureDate->greaterThan($today)) {
              // Marcamos que está com erro
              $save = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'date' => 'A data de assinatura do contrato não pode '
                    . 'ser uma data futura'
                ],
                'signaturedate'
              );
            }

            if ($contractData['enddate']) {
              $endDate = Carbon::createFromFormat('d/m/Y',
                $contractData['enddate']
              );
              if ($endDate->greaterThan($today)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data de encerramento deste contrato '
                      . 'não pode ser uma data futura'
                  ],
                  'enddate'
                );
              }
              
              if ($endDate->lessThan($signatureDate)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data de encerramento deste contrato '
                      . 'não pode ser inferior à data de início'
                  ],
                  'enddate'
                );
              }
            }

            if ($contract['signaturedate'] !== $contractData['signaturedate']) {
              // Calcula a data do próximo reajuste em função da data de
              // assinatura
              $dateOfNextReadjustment = Carbon::createFromFormat('d/m/Y',
                $contractData['signaturedate']
              );
              do {
                // Acrescentamos uma quantidade de meses definida no
                // período de reajuste do plano até que a data seja
                // futura
                $dateOfNextReadjustment
                  ->addMonths($contract['readjustmentperiod'])
                ;
              } while ($dateOfNextReadjustment->lessThan($today));

              $contractData['dateofnextreadjustment'] =
                $dateOfNextReadjustment
                  ->format('d/m/Y')
              ;
              $changeNextReadjustment = true;
            }
          }

          if ($contractData['geographiccoordinateid'] == 0) {
            // Verificamos se foram informados os valores do novo ponto
            // de referência
            if (strlen($contractData['referencename']) == 0) {
              // Marcamos que está com erro
              $save = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'blank' => 'O nome da referência precisa ser '
                    . 'informada'
                ],
                'referencename'
              );
            } else {
              if (GeographicCoordinate::where("contractorid", '=', $contractor->id)
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$contractData['referencename']}')"
                      )
                    ->count() !== 0) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'blank' => 'O nome da referência já existe'
                  ],
                  'referencename'
                );
              }
            }
            if (strlen($contractData['latitude']) == 0) {
              // Marcamos que está com erro
              $save = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'blank' => 'A latitude precisa ser informada'
                ],
                'latitude'
              );
            }
            if (strlen($contractData['longitude']) == 0) {
              // Marcamos que está com erro
              $save = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'blank' => 'A longitude precisa ser informada'
                ],
                'longitude'
              );
            }
          }

          if ($save) {
            // Grava as informações do contrato

            // Precisa retirar dos parâmetros as informações
            // correspondentes às outras tabelas
            $chargesData = $contractData['charges'];
            unset($contractData['charges']);
            // $featuresData = $contractData['features'];
            // unset($contractData['features']);
            $displacementsData = $contractData['displacements'];
            unset($contractData['displacements']);

            // Não permite modificar o contratante
            unset($contractData['contractorid']);
            if (!$changeNextReadjustment) {
              unset($contractData['dateofnextreadjustment']);
            }

            if ($contractData['geographiccoordinateid'] == 0) {
              // Precisamos acrescentar a nova coordenada geográfica
              $referenceName = $contractData['referencename'];
              $latitude      = $this->toFloat($contractData['latitude']);
              $longitude     = $this->toFloat($contractData['longitude']);

              $sql = ""
                . "INSERT INTO erp.geographicCoordinates"
                . "       (contractorID, name, location) VALUES"
                . "       ({$contractor->id}, '{$referenceName}', "
                . "        point({$latitude}, {$longitude}))"
                . "RETURNING geographicCoordinateID;"
              ;
              $coordinate = $this->DB->select($sql);
              $newCoordinateID = $coordinate[0]->geographiccoordinateid;
              $contractData['geographiccoordinateid'] = $newCoordinateID;
              unset($sql);
            }
            
            // ================================[ Valores Cobrados ]=====
            // Recupera as informações dos valores cobrados e separa os
            // dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----
            
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
                if (empty($charge['contractchargeid'])) {
                  // Adiciona o tipo de cobrança
                  $newCharges[] = $charge;
                } else {
                  // Atualiza o tipo de cobrança
                  $updCharges[]  = $charge;
                }
              } else {
                if (!empty($charge['contractchargeid'])) {
                  // Remove o tipo de cobrança
                  $delCharges[] = $charge['contractchargeid'];
                }
              }
            }
            
            // ==============[ Faixas de cobrança de deslocamento ]=====
            // Recupera as informações das faixas de cobrança de
            // deslocamento e separa os dados para as operações de
            // inserção, atualização e remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----

            // Modifica a distância da primeira faixa de cobrança para
            // nula pois ela é considerada a faixa padrão, ou seja, caso
            // não sejam definidas outras faixas, é ela quem prevalece.
            // Caso sejam definidas outras faixas, ela é a última faixa.
            $displacementsData[0]['distance'] = null;

            // Analisa as faixas de cobrança informadas, de forma a
            // separar quais valores precisam ser adicionados, removidos
            // e atualizados
            
            // Matrizes que armazenarão os dados das faixas de cobrança
            // a serem adicionadas, atualizadas e removidas
            $newDisplacements = [ ];
            $updDisplacements = [ ];
            $delDisplacements = [ ];

            // Os IDs das faixas de cobrança mantidas para permitir
            // determinar as faixas a serem removidas
            $heldDisplacements = [ ];

            // Determina quais faixas de cobrança serão mantidas (e
            // atualizadas) e as que precisam ser adicionadas (novas)
            foreach ($displacementsData AS $displacement) {
              if (empty($displacement['displacementfeeid'])) {
                // Faixa de cobrança novo
                $newDisplacements[] = $displacement;
              } else {
                // Faixa de cobrança existente
                $heldDisplacements[] = $displacement['displacementfeeid'];
                $updDisplacements[]  = $displacement;
              }
            }
            
            // Recupera as faixas de cobrança armazenadas atualmente
            $displacements = DisplacementFee::where(
                  'contractid', $contractID
                )
              ->orderByRaw('distance NULLS FIRST')
              ->get(['displacementfeeid'])
              ->toArray()
            ;

            $oldDisplacements = [ ];
            foreach ($displacements as $displacement) {
              $oldDisplacements[] = $displacement['displacementfeeid'];
            }

            // Verifica quais as faixas de cobrança estavam na base de
            // dados e precisam ser removidas
            $delDisplacements = array_diff(
              $oldDisplacements, $heldDisplacements
            );

            // ========================================[ Gravação ]=====

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // ----------------------------------------[ Contrato ]-----
            // Grava as informações do contrato
            $contractChanged = Contract::findOrFail($contractID);
            $contractChanged->fill($contractData);
            // Adiciona o usuário responsável pela modificação
            $contractChanged->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $contractChanged->save();
            
            // --------------------------------[ Valores cobrados ]-----
            
            // Primeiro apagamos os valores cobrados removidos pelo
            // usuário durante a edição
            foreach ($delCharges as $chargeID) {
              // Apaga cada valor cobrado
              $charge = ContractCharge::findOrFail($chargeID);
              $charge->delete();
            }

            // Agora inserimos os novos valores cobrados
            foreach ($newCharges as $chargeData) {
              // Incluímos um novo valor cobrado neste contrato
              unset($chargeData['contractchargeid']);
              $charge = new ContractCharge();
              $charge->fill($chargeData);
              $charge->contractid = $contractID;
              $charge->planchargeid = ($chargeData['planchargeid'] === 0)
                ? NULL
                : $charge->planchargeid
              ;
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
              $chargeID = $chargeData['contractchargeid'];
              unset($chargeData['contractchargeid']);
              
              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe nem do contratante
              unset($chargeData['contractorid']);
              unset($chargeData['contractid']);
              
              // Grava as informações do valor cobrado
              $charge = ContractCharge::findOrFail($chargeID);
              $charge->fill($chargeData);
              $charge->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $charge->save();
            }
            
            // --------------[ Faixas de cobrança de deslocamento ]-----

            // Primeiro apagamos as faixas de cobrança de deslocamento
            // removidas pelo usuário durante a edição
            foreach ($delDisplacements as $displacementFeeID) {
              // Apaga cada faixa definida
              $displacement = DisplacementFee::findOrFail($displacementFeeID);
              $displacement->delete();
            }

            // Agora inserimos as novas faixas de cobrança definidas
            foreach ($newDisplacements as $displacementData) {
              // Incluímos uma nova faixa de cobrança de deslocamento
              // neste contrato
              unset($displacementData['displacementfeeid']);

              $displacement = new DisplacementFee();
              $displacement->fill($displacementData);
              $displacement->contractid = $contractID;
              $displacement->save();
            }

            // Por último, modificamos as faixas de cobrança de
            // deslocamento mantidas
            foreach($updDisplacements AS $displacementData) {
              // Retira a ID da faixa
              $displacementFeeID = $displacementData['displacementfeeid'];
              unset($displacementData['displacementfeeid']);
              
              // Por segurança, nunca permite modificar qual a ID do
              // contrato
              unset($displacementData['contractid']);
              
              // Grava as informações da faixa de cobrança
              $displacement = DisplacementFee::findOrFail($displacementFeeID);
              $displacement->fill($displacementData);
              $displacement->save();
            }

            // ---------------------------------------------------------

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Modificado o contrato nº '{number}' do "
              . "cliente '{customername}' no contratante {contractor}.",
              [ 'number' => $contract['contractnumber'],
                'customername' => $contract['customername'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O contrato nº <i>'{number}'</i> "
              . "do cliente <i>{customername}<i> foi modificado com "
              . "sucesso.",
              [ 'number' => $contract['contractnumber'],
                'customername' => $contract['customername'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Financial\Contracts' ]
            );
            
            // Redireciona para a página de gerenciamento de contratos
            return $this->redirect($response,
              'ERP\Financial\Contracts'
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "contrato '{number}' do cliente '{customername}' no "
            . "contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}",
            [ 'number' => $contract['contractnumber'],
              'customername' => $contract['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do contrato. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "contrato '{number}' do cliente '{customername}' no "
            . "contratante '{contractor}'. Erro interno: {error}",
            [ 'number' => $contract['contractnumber'],
              'customername' => $contract['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do contrato. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do contrato são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($contract);
    }
    
    // Exibe um formulário para edição de um contrato
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Contratos',
      $this->path('ERP\Financial\Contracts')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Financial\Contracts\Edit', [
        'contractID' => $contractID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do contrato '{number}' do cliente "
      . "'{customername}' no contratante {contractor}.",
      [ 'number' => $contract['contractnumber'],
        'customername' => $contract['customername'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/contracts/contract.twig',
      [
        'formMethod' => 'PUT',
        'dueDays' => $dueDays,
        'paymentConditions' => $paymentConditions,
        'billingTypes' => $billingTypes,
        'measureTypes' => $measureTypes,
        'timeTypes' => $timeTypes,
        'geographicCoordinates' => $geographicCoordinates
      ])
    ;
  }
  
  /**
   * Remove o contrato.
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
    $this->debug("Processando à remoção de contrato.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $contractID = $args['contractID'];

    try
    {
      // Recupera as informações do contrato
      $contractData = Contract::join('entities as customer',
            'contracts.customerid', '=', 'customer.entityid'
          )
        ->where('contracts.contractid', '=', $contractID)
        ->get([
            'contracts.contractid AS id',
            'customer.name AS customername',
            $this->DB->raw('getContractNumber(contracts.createdat) AS number'),
          ])
        ->first()
      ;

      $contract = Contract::where('contractorid',
            '=', $contractor->id
          )
        ->where('contractid', '=', $contractID)
        ->firstOrFail()
      ;
      
      // Agora apaga o contrato

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Agora apaga o contrato e os valores relacionados
      $contract->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O contrato '{number}' do cliente '{customername}' "
        . "do contratante '{contractor}' foi removido com sucesso.",
        [ 'name' => $contractData->number,
          'customername' => $contractData->customername,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o contrato "
              . "{$contractData->number}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o contrato "
        . "código {contractID} para remoção.",
        [ 'contractID' => $contractID ]
      );
      
      $message = "Não foi possível localizar o contrato para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "contrato nº '{number}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'number'  => $contractData->number,
          'customername'  => $contractData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o contrato. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "contrato nº '{number}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'number'  => $contractData->number,
          'customername'  => $contractData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o contrato. Erro interno.";
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
   * Alterna o estado da ativação de um contrato de um
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
      . "contrato."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $contractID = $args['contractID'];
    
    try
    {
      // Recupera as informações do contrato
      $contract = Contract::where('contractorid',
            '=', $contractor->id
          )
        ->where('contractid', '=', $contractID)
        ->firstOrFail()
      ;
      
      // Alterna o estado da ativação do contrato
      $action     = $contract->active
        ? "desativado"
        : "ativado"
      ;
      $contract->active = !$contract->active;

      // Adiciona o usuário responsável pela modificação
      $contract->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $contract->save();
      
      // Registra o sucesso
      $this->info("O contrato '{name}' do contratante "
        . "'{contractor}' foi {action} com sucesso.",
        [ 'name' => $contract->name,
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
            'message' => "O contrato {$contract->name} foi "
              . "{$action} com sucesso.",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o contrato "
        . "código {contractID} no contratante '{contractor}' para "
        . "alternar o estado da ativação.",
        [ 'contractID' => $contractID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar o contrato para "
        . "alternar o estado da ativação."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do contrato '{name}' no contratante '{contractor}'. "
        . "Erro interno no banco de dados: {error}.",
        [ 'name'  => $contract->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "contrato. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado da ativação "
        . "do contrato '{name}' no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'name'  => $contract->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado da ativação do "
        . "contrato. Erro interno."
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
   * Recupera a relação de informações de um contrato em formato JSON no
   * padrão dos campos de preenchimento automático.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Relação de cobranças de um contrato para "
      . "preenchimento automático despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    //$contractor   = $this->authorization->getContractor();
    //$contractorID = $contractor->id;
    
    // Lida com as informações provenientes da solicitação
    
    // O termo de pesquisa (normalmente o nome ou parte do nome da
    // entidade a ser localizada)
    $searchTerm   = $postParams['searchTerm'];

    // O tipo da entidade que estamos tentando localizar
    $type = 'charge';
    if (isset($postParams['type'])) {
      $type = $postParams['type'];
    }
    
    // Determina os limites e parâmetros da consulta
    // Desativamos por não usar os limites de consulta para contratos
    // $start  = 0;
    // $length = 1;
    // if (isset($postParams['limit'])) {
    //   $length = $postParams['limit'];
    // }
    $contractID = 0;
    if (isset($postParams['contractID'])) {
      $contractID = $postParams['contractID'];
    }

    // Registra o acesso
    $typeNames = [
      'contract' => 'contratos',
      'charge'   => 'valores cobrados'
    ];

    $this->debug("Acesso aos dados de preenchimento automático de "
      . "{type} que contenha(m) '{name}'",
      [ 'type' => $typeNames[$type],
        'name' => $searchTerm ]
    );
    
    try
    {
      switch ($type) {
        case 'contract':
          // Localiza os contratos
          $contracts = [];

          break;
        default:
          // Localiza os valores cobrados em um contrato
          $contracts = ContractCharge::join('billingtypes',
                'contractcharges.billingtypeid', '=',
                'billingtypes.billingtypeid'
              )
            ->leftJoin('installmenttypes', 'billingtypes.installmenttypeid',
                '=', 'installmenttypes.installmenttypeid'
              )
            ->where('contractcharges.contractid', $contractID)
            ->whereRaw("public.unaccented(billingtypes.name) "
                . "ILIKE public.unaccented('%{$searchTerm}%')"
              )
            ->get([
              'contractcharges.contractchargeid AS id',
              'billingtypes.name',
              'contractcharges.chargevalue',
              $this->DB->raw(""
                . "CASE"
                . "  WHEN billingtypes.installmenttypeid > 0 THEN billingtypes.installmenttypeid "
                . "  ELSE 0 "
                . "END AS installmenttypeid"
              ),
              $this->DB->raw(""
                . "CASE"
                . "  WHEN billingtypes.installmenttypeid > 0 THEN installmenttypes.name "
                . "  ELSE 'Não disponível' "
                . "END AS installment"
              )
            ])
          ;

          if ( $contracts->isEmpty() ) {
            $contracts = [];
          } else {
            // Convertemos para matriz
            $contracts = $contracts
              ->toArray()
            ;
          }

          break;
      }
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => ucfirst($typeNames[$type]) . " cujo nome "
              . "contém '{$searchTerm}'",
            'data' => $contracts
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . $typeNames[$type] . " para preenchimento automático. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . $typeNames[$type] . " para preenchimento automático. "
        . "Erro interno."
      ;
    }
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => $contracts
        ])
    ;
  }
}
