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
 * O controlador do gerenciamento de instalações.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\Contract;
use App\Models\Equipment;
use App\Models\Installation;
use App\Models\InstallationRecord;
use App\Models\MeasureType;
use App\Models\Subsidy;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use RuntimeException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class InstallationsController
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
      'contractnumber' => V::notBlank()
        ->setName('Número do contrato'),
      'customername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do cliente'),
      'entitytypeid' => V::notBlank()
        ->intVal()
        ->setName('ID do tipo de entidade'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 50)
        ->setName('Unidade/Filial'),
      'regionaldocumenttypename'=> V::optional(
            V::notEmpty()
          )
        ->setName('Tipo do documento'),
      'regionaldocumentnumber'=> V::optional(
            V::notEmpty()
          )
        ->setName('Número do documento'),
      'regionaldocumentstate'=> V::optional(
            V::notEmpty()
          )
        ->setName('UF do documento'),
      'nationalregister'=> V::optional(
            V::notEmpty()
          )
        ->setName('CPF/CNPJ'),
      'contractnumber'=> V::notEmpty()
        ->setName('Nº do contrato'),
      'planname'=> V::notEmpty()
        ->setName('Plano de serviços'),
      'subscriptionplanname'=> V::notEmpty()
        ->setName('Forma de assinatura do plano'),
      'dueday' => V::notBlank()
        ->intVal()
        ->setName('Dia de vencimento'),
      'planmonthprice'=> V::notEmpty()
        ->setName('Valor do plano'),
      'contractmonthprice'=> V::notEmpty()
        ->setName('Mensalidade'),
      'discountrate'=> V::notEmpty()
        ->setName('Desconto oferecido'),
      'realmonthprice'=> V::notEmpty()
        ->setName('Valor pago'),
      'signaturelabel'=> V::notEmpty()
        ->setName('Data de assinatura'),
      'signaturedate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data da assinatura'),
      'duration'=> V::notEmpty()
        ->setName('Duração do contrato'),
      'readjustmentperiod'=> V::notEmpty()
        ->setName('Período de reajuste'),
      'indicatorname'=> V::notEmpty()
        ->setName('Indicador financeiro usado no reajuste'),
      'dateofnextcontractreadjustment' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data do próximo reajuste do contrato'),
      'installationid' => V::notBlank()
        ->intVal()
        ->setName('ID do contrato'),
      'installationnumber' => V::notBlank()
        ->setName('Número do contrato'),
      'startdate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data de início'),
      'enddate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data de encerramento'),
      'lastdayofbillingperiod' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Último dia do período pago'),
      'monthprice'=> V::notEmpty()
        ->setName('Mensalidade'),
      'effectivepricedate' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Início da vigência do valor cobrado nesta instalação'),
      'dateofnextreadjustment' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data do próximo reajuste nesta instalação'),
      'lastdayofcalculatedperiod' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data do último dia do período computado'),
      'notchargeloyaltybreak' => V::boolVal()
        ->setName('Não cobrar multa por quebra de fidelidade'),
      'records' => [
        'installationrecordid' => V::intVal()
          ->setName('ID do registro de instalação'),
        'vehicleid' => V::intVal()
          ->setName('ID do equipamento'),
        'plate' => V::notBlank()
          ->setName('Placa'),
        'vehiclebrandname' => V::notBlank()
          ->setName('Marca do veículo'),
        'vehiclemodelname' => V::notBlank()
          ->setName('Modelo do veículo'),
        'equipmentid' => V::intVal()
          ->setName('ID do equipamento'),
        'serialnumber' => V::notBlank()
          ->setName('Número de série do equipamento'),
        'equipmentbrandname' => V::notBlank()
          ->setName('Marca do equipamento'),
        'equipmentmodelname' => V::notBlank()
          ->setName('Modelo do equipamento'),
        'installedat' => V::notEmpty()
          ->date('d/m/Y')
          ->setName('Data de instalação'),
        'uninstalledat' => V::optional(
              V::notEmpty()
                ->date('d/m/Y')
            )
          ->setName('Data de desinstalação')
      ],
      'subsidies' => [
        'subsidyid' => V::intVal()
          ->setName('ID do subsídio'),
        'periodstartedat' => V::notEmpty()
          ->date('d/m/Y')
          ->setName('Início do período'),
        'periodendedat' => V::optional(
              V::notEmpty()
                ->date('d/m/Y')
            )
          ->setName('Término do período'),
        'bonus' => V::boolVal()
          ->setName('Bonificação'),
        'discounttype' => V::intVal()
          ->min(1)
          ->setName('Tipo do desconto'),
        'discountvalue' => V::numericValue()
          ->setName('Valor do desconto')
      ]
    ];

    if ($addition) {
      // Ajusta as regras para adição de um novo contrato
    } else {
      // Ajusta as regras para edição de um contrato
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de medidas.
   *
   * @return Collection
   *   A matriz com as informações de tipos de medidas
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de medidas
   */
  protected function getMeasureTypes(): Collection
  {
    try {
      // Recupera as informações de características técnicas
      $measureTypes = MeasureType::orderBy("measuretypeid")
        ->get([
            'measuretypeid as id',
            'name',
            'symbol'
          ])
      ;

      if ( $measureTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de medida "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "tipos de medidas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as "
        . "tipos de medidas"
      );
    }

    return $measureTypes;
  }
  
  /**
   * Recupera a relação dos instalações em formato JSON.
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
    $this->debug("Acesso à relação de instalações.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = (array) $request->getParsedBody();

    if (isset($postParams['request'])) {
      // Lida com uma requisição de um dropdown
      $contractID = $postParams['contractID'];
      $includeInstalled = false;
      if (isset($postParams['includeInstalled'])) {
        $includeInstalled = $postParams['includeInstalled']==='true'
          ? true
          : false
        ;
      }
      $includeNew = false;
      if (isset($postParams['includeNew'])) {
        $includeNew = $postParams['includeNew']==='true'
          ? true
          : false
        ;
      }
      $includeSuspended = 'false';
      if (isset($postParams['includeSuspended'])) {
        $includeSuspended = $postParams['includeSuspended']==='true'
          ? 'true'
          : 'false'
        ;
      }
      $includeFinish = 'false';
      if (isset($postParams['includeFinish'])) {
        $includeFinish = $postParams['includeFinish']==='true'
          ? 'true'
          : 'false'
        ;
      }

      $complement = '';
      if ($includeSuspended == 'true') {
        $complement = '(mesmo que este esteja suspenso)';
      } else {
        $complement = '(se o contrato estiver ativo)';
      }
      if ($includeFinish == 'true') {
        $complement = ' incluindo itens estejam encerrados';
      } else {
        $complement = ' que estejam ativos';
      }
      if ($includeInstalled) {
        $complement = ' e que contenham rastreador instalado';
      }
      $this->debug("Requisitando os itens do contrato "
        . "{contractID} {complement}",
        [
          'contractID' => $contractID,
          'complement' => $complement
        ]
      );

      $sql = "SELECT I.id,
                     I.installationNumber,
                     I.plate,
                     I.startDate,
                     I.termination,
                     I.noTracker,
                     I.finish
                FROM (
                  SELECT installationID AS id,
                         installationNumber,
                         plate,
                         startDate,
                         CASE
                           WHEN endDate IS NULL THEN 'Ativo'
                           ELSE 'Encerrado em ' || to_char(endDate, 'DD/MM/YYYY')
                         END AS termination,
                         noTracker,
                         finish
                    FROM erp.getInstallationsData({$contractID}, {$includeSuspended}, {$includeFinish}, 0)
                   ORDER BY noTracker DESC, plate, startDate ASC
                  ) AS I;"
      ;
      $installations = $this->DB->select($sql);

      $hasNew = false;
      if ( count($installations) > 0 ) {
        $results = [];
        foreach ($installations AS $installation) {
          if ($installation->startdate) {
            if ($installation->finish) {
              $description = '<span style="color: DarkRed;">' . $installation->termination . '</span>';
            } else {
              if ($installation->notracker) {
                $description = 'Sem rastreador';
              } else {
                if ($includeInstalled) {
                  $description = 'Instalado em ' . $this->formatSQLDate($installation->startdate);
                } else {
                  continue;
                }
              }
            }
          } else {
            $description = 'Não instalado';
            $hasNew = true;
          }

          $plate = ($installation->plate)
            ? $installation->plate
            : ''
          ;
          $description = ''
            . '<div class="hidden">'
            .   $description . '<br>'
            . '</div>'
            . '<span class="plate">'
            .   $plate
            . '</span>'
          ;

          $results[] = [
            'name' => $installation->installationnumber,
            'value' => $installation->id,
            'description' => $description,
            'descriptionVertical' => true
          ];
        }
      } else {
        $results = [];
      }

      if ($includeNew && !$hasNew) {
        array_unshift($results, [
          'name' => 'Novo',
          'value' => 0,
          'description' => ''
            . '<div class="hidden">'
            .   'Cria um novo item neste contrato<br>'
            . '</div>'
            . '<span class="plate">'
            . '</span>'
          ,
          'descriptionVertical' => true
        ]);
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
    $customerID   = $postParams['customerID'];
    //$customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;
    //$subsidiaryName = array_key_exists('subsidiaryName', $postParams)
    //  ? $postParams['subsidiaryName']
    //  : ''
    //;
    $toCarnet = isset($postParams['toCarnet'])
      ? 'true'
      : 'false'
    ;
    $withoutInactiveInstallations = (array_key_exists('withoutInactive', $postParams)==true)
      ? 'true'
      : 'false'
    ;

    $fromAssociate = isset($postParams['fromAssociate'])
      ? (($postParams['fromAssociate']==='true')?true:false)
      : false
    ;
    $associateID = isset($postParams['associateID'])
      ? (int) $postParams['associateID']
      : 0
    ;

    $searchValue = 'NULL';
    $searchField = 'NULL';
    if ($fromAssociate) {
      $searchValue = "'{$associateID}'";
      $searchField = "'associate'";
      $this->debug("Selecionando instalações do associado {$associateID}");
    }

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido dos campos de pesquisa
      if ($customerID > 0) {
        // Realiza a consulta
        $contractorID = $this->authorization->getContractor()->id;
        $sql = "
         SELECT '' AS selected,
                C.installationID AS id,
                C.installationNumber,
                CASE
                  WHEN C.startDate IS NULL THEN NULL
                  ELSE to_char(C.startDate, 'DD/MM/YYYY')
                END AS startDate,
                CASE
                  WHEN C.endDate IS NULL THEN NULL
                  ELSE to_char(C.endDate, 'DD/MM/YYYY')
                END AS endDate,
                C.contractID,
                to_char(C.signatureDate, 'DD/MM/YYYY') AS signatureDate,
                CASE
                 WHEN C.contractendDate IS NULL THEN NULL
                 ELSE to_char(C.contractendDate, 'DD/MM/YYYY')
                END AS contractendDate,
                C.dueDay,
                C.monthPrice,
                C.paymentConditionID,
                C.numberOfParcels,
                to_char(C.firstDueDate, 'DD/MM/YYYY') AS firstDueDate,
                CASE
                  WHEN C.lastDayOfBillingPeriod IS NULL THEN NULL
                  ELSE to_char(C.lastDayOfBillingPeriod, 'DD/MM/YYYY')
                END AS lastDayOfBillingPeriod,
                C.plate,
                C.vehicleBrandName || '/' || C.vehicleModelName AS model,
                C.vehicleBlocked,
                C.noTracker,
                C.containsTrackingData,
                C.blockedlevel,
                C.fullcount
           FROM erp.getContractsData({$contractorID}, {$customerID},
                    {$subsidiaryID}, 0, $searchValue, $searchField,
                    NULL, {$toCarnet}, $withoutInactiveInstallations,
                    '{$ORDER}', {$start}, {$length}) AS C;"
        ;
        $contracts = $this->DB->select($sql);
        $rowCount = count($contracts) > 0
          ? $contracts[0]->fullcount
          : 0
        ;
      } else {
        $contracts = [];
        $rowCount = 0;
      }

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'draw' => $draw,
            'recordsTotal' => $rowCount,
            'recordsFiltered' => $rowCount,
            'data' => $contracts
          ])
      ;
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    try
    {
      // Recupera as informações do contrato
      $contractID = $args['contractID'];
      $contract = Contract::join('entities AS customers',
            'contracts.customerid', '=', 'customers.entityid'
          )
        ->where('contracts.contractorid', '=', $contractor->id)
        ->where('contracts.contractid', '=', $contractID)
        ->get([
            'contracts.contractid',
            $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
            'contracts.planid',
            'contracts.subscriptionplanid',
            'contracts.monthprice',
            'contracts.customerid',
            'customers.name AS customername',
            'contracts.subsidiaryid'
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

      // Grava a nova instalação
      $userID = $this->authorization->getUser()->userid;
      
      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Incluímos uma nova instalação
      $installation = new Installation();
      $installation->contractorid       = $contractor->id;
      $installation->customerid         = $contract['customerid'];
      $installation->subsidiaryid       = $contract['subsidiaryid'];
      $installation->contractid         = $contractID;
      $installation->planid             = $contract['planid'];
      $installation->subscriptionplanid = $contract['subscriptionplanid'];
      $installation->monthprice         = $contract['monthprice'];
      $installation->createdbyuserid = $userID;
      $installation->updatedbyuserid = $userID;
      $installation->save();
      $installationID = $installation->installationid;

      // Atualizamos o número da instalação
      $sql = ""
        . "UPDATE erp.installations
              SET installationnumber = erp.generateInstallationNumber(contractorID, contractID, installationID)
            WHERE installations.installationID = {$installationID};"
      ;
      $this->DB->select($sql);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("Cadastrada uma nova instalação no contrato "
        . "'{number}' do cliente '{customerName}' no contratante "
        . "'{contractor}' com sucesso.",
        [ 'number' => $contract['contractnumber'],
          'customerName' => $contract['customername'],
          'contractor' => $contractor->name ]
      );
      
      // Informa que a adição foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Adicionada instalação no contrato "
              . "{$contract['contractnumber']}",
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
        . "código {contractID} para adicionar instalação.",
        [ 'contractID' => $contractID ]
      );
      
      $message = "Não foi possível localizar o contrato para "
        . "adição de instalação."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível inserir uma nova instalação no "
        . "contrato '{number}' do cliente '{customerName}' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'number' => $contract['contractnumber'],
          'customerName' => $contract['customername'],
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível adicionar uma instalação no "
        . "contrato. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível inserir uma nova instalação no "
        . "contrato '{number}' do cliente '{customerName}' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'number' => $contract['contractnumber'],
          'customerName' => $contract['customername'],
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível adicionar uma instalação no "
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

      // Recupera os tipos de medidas
      $measureTypes = $this->getMeasureTypes();
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
      // Recupera as informações da instalação
      $installationID = $args['installationID'];
      $installation = Installation::join('contracts',
            'installations.contractid', '=', 'contracts.contractid'
          )
        ->join('entities AS customers',
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
        ->join('plans', 'contracts.planid',
            '=', 'plans.planid'
          )
        ->join('duedays', 'contracts.duedayid',
            '=', 'duedays.duedayid'
          )
        ->join('subscriptionplans', 'contracts.subscriptionplanid',
            '=', 'subscriptionplans.subscriptionplanid'
          )
        ->join('indicators', 'plans.indicatorid',
            '=', 'indicators.indicatorid'
          )
        ->join('users AS createduser', 'contracts.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'contracts.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('contracts.contractorid', '=', $contractor->id)
        ->where('installations.installationid', '=', $installationID)
        ->get([
            'installations.contractid',
            $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
            'contracts.monthprice AS contractmonthprice',
            $this->DB->raw("to_char(contracts.signaturedate, 'DD/MM/YYYY') AS signaturedate"),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN contracts.signaturedate IS NULL THEN 'Não assinado' "
              . "  ELSE to_char(contracts.signaturedate, 'DD/MM/YYYY') "
              . "END AS signaturelabel"
            ),
            $this->DB->raw("to_char(contracts.dateofnextreadjustment, 'DD/MM/YYYY') AS dateofnextcontractreadjustment"),
            $this->DB->raw("to_char(installations.dateofnextreadjustment, 'DD/MM/YYYY') AS dateofnextinstallationreadjustment"),
            'customers.name AS customername',
            'customers.entitytypeid',
            'entitiestypes.juridicalperson as juridicalperson',
            'subsidiaries.name AS subsidiaryname',
            'documenttypes.name AS regionaldocumenttypename',
            'subsidiaries.regionaldocumentnumber',
            'subsidiaries.regionaldocumentstate',
            'subsidiaries.nationalregister',
            'plans.name AS planname',
            'plans.monthprice AS planmonthprice',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN subscriptionplans.numberofmonths > 1 THEN 'Assinado por ' || subscriptionplans.numberofmonths || ' meses' "
              . "  ELSE 'Pagamentos mensais' "
              . "END AS subscriptionplanname"
            ),
            $this->DB->raw("trim(to_char(installations.monthprice - (installations.monthprice*subscriptionplans.discountrate/100), '9999999999D99')) AS realmonthprice"),
            'subscriptionplans.discountrate',
            'plans.duration',
            'plans.indicatorid',
            'indicators.name AS indicatorname',
            'plans.readjustmentperiod',
            'plans.loyaltyperiod',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN to_char(installations.startdate + plans.loyaltyperiod * interval '1 month', 'DD/MM/YYYY')"
              . "  ELSE NULL  "
              . "END AS endloyaltyperiod"
            ),
            'contracts.notchargeloyaltybreak AS notchargeloyaltybreakinallcontract',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN contracts.notchargeloyaltybreak THEN TRUE"
              . "  ELSE installations.notchargeloyaltybreak "
              . "END AS notchargeloyaltybreak"
            ),
            'duedays.day AS dueday',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername',
            'installations.installationid',
            'installations.installationnumber',
            'installations.monthprice',
            'installations.startdate',
            'installations.enddate',
            'installations.effectivepricedate',
            'installations.dateofnextreadjustment',
            'installations.lastdayofcalculatedperiod',
            'installations.lastdayofbillingperiod'
          ])
      ;

      if ( $installation->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum contrato com "
          . "o código {$installationID} cadastrado"
        );
      }
      $installation = $installation
        ->first()
        ->toArray()
      ;

      // Agora recupera as informações dos equipamentos
      $installation['records'] = InstallationRecord::join('vehicles',
            'installationrecords.vehicleid', '=', 'vehicles.vehicleid'
          )
        ->join('vehiclebrands', 'vehicles.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('vehiclemodels', 'vehicles.vehiclemodelid',
            '=', 'vehiclemodels.vehiclemodelid'
          )
        ->join('equipments', 'installationrecords.equipmentid',
            '=', 'equipments.equipmentid'
          )
        ->join('equipmentmodels',
            'equipments.equipmentmodelid', '=',
            'equipmentmodels.equipmentmodelid'
          )
        ->join('equipmentbrands', 'equipmentmodels.equipmentbrandid',
            '=', 'equipmentbrands.equipmentbrandid'
          )
        ->where('installationrecords.installationid', $installationID)
        ->orderByRaw('installationrecords.installedat, installationrecords.uninstalledat NULLS LAST')
        ->get([
            'installationrecords.installationrecordid',
            'installationrecords.vehicleid',
            'vehicles.plate',
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'installationrecords.equipmentid',
            'equipments.serialnumber',
            'equipmentbrands.name AS equipmentbrandname',
            'equipmentmodels.name AS equipmentmodelname',
            'installationrecords.installedat',
            'installationrecords.uninstalledat'
          ])
        ->toArray()
      ;

      // Subsídios
      $installation['subsidies'] = Subsidy::join('measuretypes',
            'subsidies.discounttype', '=', 'measuretypes.measuretypeid'
          )
        ->where('subsidies.installationid', $installationID)
        ->orderByRaw('subsidies.periodstartedat, subsidies.periodendedat NULLS FIRST')
        ->get([
            'subsidies.subsidyid',
            'subsidies.periodstartedat',
            'subsidies.periodendedat',
            'subsidies.bonus',
            'subsidies.discounttype',
            'measuretypes.name AS discounttypename',
            'measuretypes.symbol AS discounttypelabel',
            'subsidies.discountvalue'
          ])
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a instalação código "
        . "{installationID}.",
        [ 'installationID' => $installationID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta "
        . "instalação."
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
      $this->debug("Processando à edição da instalação '{installation}' "
        . "do contrato '{number}' do cliente '{customername}' no "
        . "contratante {contractor}.",
        [ 'installation' => $installation['installationnumber'],
          'number' => $installation['contractnumber'],
          'customername' => $installation['customername'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados da instalação são VÁLIDOS');

        // Grava as informações no banco de dados

        // Recupera os dados modificados da instalação
        $installationData = $this->validator->getValues();

        try
        {
          // Precisa retirar dos parâmetros as informações
          // correspondentes aos registros de instalações e de
          // subsídios
          $recordsData = $installationData['records'];
          unset($installationData['records']);
          if (array_key_exists('subsidies', $installationData)) {
            $subsidiesData = $installationData['subsidies'];
            unset($installationData['subsidies']);
          } else {
            $subsidiesData = [];
          }

          // Inicia parâmetros de análise
          $save = true;
          $changeNextReadjustment = false;

          // Verifica as informações de data
          if ($installationData['startdate']) {
            $today = Carbon::now();
            $startDate = Carbon::createFromFormat('d/m/Y',
              $installationData['startdate']
            );

            if ($startDate->greaterThan($today)) {
              // Marcamos que está com erro
              $save = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'date' => 'A data de início desta instalação '
                    . 'não pode ser uma data futura'
                ],
                'startdate'
              );
            }

            if ($installationData['enddate']) {
              $endDate = Carbon::createFromFormat('d/m/Y',
                $installationData['enddate']
              );

              if ($endDate->greaterThan($today)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data de término desta instalação não '
                      . 'pode ser uma data futura'
                  ],
                  'enddate'
                );
              }

              if ($endDate->lessThan($startDate)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data de término desta instalação não '
                      . 'pode ser inferior à data de início da '
                      . 'instalação'
                  ],
                  'enddate'
                );
              }
            }

            if ($installation['startdate'] !== $installationData['startdate']) {
              // Calcula a data do próximo reajuste em função da data de
              // instalação
              $dateOfNextReadjustment = Carbon::createFromFormat('d/m/Y',
                $installationData['startdate']
              );

              do {
                // Acrescentamos uma quantidade de meses definida no
                // período de reajuste do plano até que a data seja
                // futura
                $dateOfNextReadjustment
                  ->addMonths($installation['readjustmentperiod'])
                ;
              } while ($dateOfNextReadjustment->lessThan($today));

              $installationData['dateofnextreadjustment'] =
                $dateOfNextReadjustment
                  ->format('d/m/Y')
              ;
              $changeNextReadjustment = true;
            }
          }

          if ($save) {
            // Analisa cada um dos registros de instalação para garantir
            // que as datas de início e término sejam válidas
            foreach ($recordsData as $recordNumber => $record) {
              $installedAt = Carbon::createFromFormat('d/m/Y',
                $record['installedat']
              );

              // Verifica se temos uma data de desinstalação deste
              // equipamento
              if ($record['uninstalledat']) {
                $uninstalledAt = Carbon::createFromFormat('d/m/Y',
                  $record['uninstalledat']
                );

                // Verifica se a data de desinstalação é inferior à data
                // de instalação (inversão das datas)
                if ($uninstalledAt->lessThan($installedAt)) {
                  // Marcamos que está com erro
                  $save = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'date' => 'A data de desinstalação do equipamento '
                        . 'não pode ser inferior à data de instalação'
                    ],
                    "records[{$recordNumber}][uninstalledat]")
                  ;
                }

                // Verifica se a data de desinstalação é inferior à data
                // de início da instalação
                if ($uninstalledAt->lessThan($startDate)) {
                  // Marcamos que está com erro
                  $save = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'date' => 'A data de desinstalação do equipamento '
                        . 'não pode ser inferior à data de início desta '
                        . 'instalação'
                    ],
                    "records[{$recordNumber}][uninstalledat]")
                  ;
                }

                // Verifica se a data de desinstalação é superior à data
                // atual (data futura)
                if ($uninstalledAt->greaterThan($today)) {
                  // Marcamos que está com erro
                  $save = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'date' => 'A data de desinstalação não pode ser '
                        . 'uma data futura'
                    ],
                    "records[{$recordNumber}][uninstalledat]")
                  ;
                }
              }

              // Verifica se a data de instalação é superior à data
              // atual
              if ($installedAt->greaterThan($today)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data de instalação do equipamento não '
                      . 'pode ser futura'
                  ],
                  "records[{$recordNumber}][installedat]")
                ;
              }
            }
          }

          if ($save) {
            // Analisa cada um dos registros de subsídios para garantir
            // que as datas de início e término sejam válidas
            foreach ($subsidiesData as $subsidyNumber => $subsidy) {
              $periodStartedAt = Carbon::createFromFormat('d/m/Y',
                $subsidy['periodstartedat']
              );

              // Verifica se temos uma data de término deste período
              if ($subsidy['periodendedat']) {
                $periodEndedAt = Carbon::createFromFormat('d/m/Y',
                  $subsidy['periodendedat']
                );

                // Verifica se a data de término é inferior à data de
                // início do período de subsídio
                if ($periodEndedAt->lessThan($periodStartedAt)) {
                  // Marcamos que está com erro
                  $save = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'date' => 'A data de término do período não pode '
                        . 'ser inferior à data de início'
                    ],
                    "subsidies[{$subsidyNumber}][periodendedat]")
                  ;
                }
              }

              // Verifica se a data do início do período é inferior à
              // data de início da instalação
              if ($periodStartedAt->lessThan($startDate)) {
                // Marcamos que está com erro
                $save = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'date' => 'A data do início do período de subsídio '
                      . 'não pode ser inferior à data de início desta '
                      . 'instalação'
                  ],
                  "subsidies[{$subsidyNumber}][periodstartedat]")
                ;
              }
            }
          }

          if ($save) {
            // Grava as informações da instalação

            // Não permite modificar o contratante e as informações que
            // são determinadas em função do processamento
            unset($installationData['contractorid']);
            unset($installationData['installationnumber']);
            unset($installationData['lastdayofbillingperiod']);
            unset($installationData['monthprice']);
            unset($installationData['effectivepricedate']);
            if (!$changeNextReadjustment) {
              unset($installationData['dateofnextreadjustment']);
            }
            unset($installationData['lastdayofcalculatedperiod']);
            
            // =========================[ Registros de instalação ]=====
            // Recupera as informações dos registros de instalações e
            // separa os dados para as operações de atualização e
            // remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----
            
            // Analisa os registros informados, de forma a separar quais
            // valores precisam ser removidos e atualizados
            
            // Matrizes que armazenarão os dados dos registros de
            // instalações a serem atualizadas e removidas
            $updRecords = [ ];
            $delRecords = [ ];

            // Os IDs dos registros de instalações mantidos para permitir
            // determinar àqueles a serem removidos
            $heldRecords = [ ];

            // Determina quais registros de instalações serão mantidos (e
            // atualizados)
            foreach ($recordsData AS $record) {
              // Valor cobrado existente
              $heldRecords[] = $record['installationrecordid'];
              $updRecords[]  = $record;
            }
            
            // Recupera os registros de instalações armazenados atualmente
            $records = InstallationRecord::where('installationid', '=',
                  $installationID
                )
              ->get(['installationrecordid'])
              ->toArray()
            ;
            $oldRecords = [ ];
            foreach ($records as $record) {
              $oldRecords[] = $record['installationrecordid'];
            }

            // Verifica quais os registros de instalações estavam na
            // base de dados e precisam ser removidos
            $delRecords = array_diff($oldRecords, $heldRecords);

            // =======================================[ Subsídios ]=====
            // Recupera as informações dos subsídios e separa os dados
            // para as operações de inserção, atualização e remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----
            
            // Analisa os registros informados, de forma a separar quais
            // valores precisam ser removidos e atualizados
            
            // Matrizes que armazenarão os dados dos subsídios a serem
            // atualizados e removidos
            $newSubsidies = [ ];
            $updSubsidies = [ ];
            $delSubsidies = [ ];

            // Os IDs dos subsídios mantidos para permitir determinar
            // àqueles a serem removidos
            $heldSubsidies = [ ];

            // Determina quais registros de subsídios serão mantidos (e
            // atualizados)
            foreach ($subsidiesData AS $subsidy) {
              if (empty($subsidy['subsidyid'])) {
                // Subsídio novo
                $newSubsidies[] = $subsidy;
              } else {
                // Subsídio existente
                $heldSubsidies[] = $subsidy['subsidyid'];
                $updSubsidies[]  = $subsidy;
              }
            }
            
            // Recupera os registros de subsídios armazenados atualmente
            $subsidies = Subsidy::where('installationid', '=',
                  $installationID
                )
              ->get(['subsidyid'])
              ->toArray()
            ;
            $oldSubsidies = [ ];
            foreach ($subsidies as $subsidy) {
              $oldSubsidies[] = $subsidy['subsidyid'];
            }

            // Verifica quais os subsídios estavam na base de dados e
            // precisam ser removidos
            $delSubsidies = array_diff($oldSubsidies, $heldSubsidies);

            // =========================================================
            
            $userID = $this->authorization->getUser()->userid;

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Grava as informações do contrato
            $installationChanged = Installation::findOrFail($installationID);
            $installationChanged->fill($installationData);
            // Adiciona o usuário responsável pela modificação
            $installationChanged->updatedbyuserid = $userID;
            $installationChanged->save();

            // ------------------------[ Registros de instalações ]-----
            
            // Primeiro apagamos os registros de instalações removidos
            // pelo usuário durante a edição
            foreach ($delRecords as $recordID) {
              // Apaga cada valor cobrado
              $record = InstallationRecord::findOrFail($recordID);
              $record->delete();
            }

            // Por último, modificamos os registros de instalações
            // mantidos
            foreach($updRecords AS $recordData) {
              // Retira a ID do valor cobrado
              $recordID = $recordData['installationrecordid'];
              unset($recordData['installationrecordid']);

              // Obtemos as datas de instalação e desinstalação deste
              // registro
              $installedAt = Carbon::createFromFormat('d/m/Y H:i:s',
                $recordData['installedat'] . " 00:00:00"
              );
              $uninstalledAt = ($recordData['uninstalledat'])
                ? Carbon::createFromFormat('d/m/Y H:i:s',
                    $recordData['uninstalledat'] . " 00:00:00"
                  )
                : null
              ;

              // Recuperamos as informações hoje armazenadas do registro
              // de instalação
              $installationRecord = InstallationRecord::findOrFail($recordID);

              // Agora analisamos as mudanças efetuadas
              $changedStart = $installedAt->notEqualTo(
                $installationRecord->installedat
              );
              $changedEnd = false;
              if ($installationRecord->uninstalledat == null) {
                // O equipamento estava em operação
                if ($uninstalledAt !== null) {
                  // Foi inserida uma data de desinstalação, então não
                  // permite e alerta
                  throw new Exception("Não é possível desvincular um "
                    . "rastreador desta forma"
                  );
                } else {
                  if ($changedStart) {
                    // Precisamos modificar o registro do equipamento de
                    // rastreamento, para manter o sincronismo
                    $equipmentID = $recordData['equipmentid'];
                    $equipment = Equipment::findOrFail($equipmentID);
                    $equipment->installedat = $installedAt;
                    $equipment->updatedbyuserid = $userID;
                    $equipment->save();
                  }
                }
              } else {
                if ($uninstalledAt == null) {
                  // Estamos indicando que o rastreador não estava
                  // desinstalado, então analisa se isto de fato está
                  // correto
                  $equipmentID = $recordData['equipmentid'];
                  $equipment = Equipment::findOrFail($equipmentID);
                  if ($equipment->storagelocation == 'Installed') {
                    if ($equipment->vehicleid == $recordData['vehicleid']) {
                      // O equipamento permanece instalado neste veículo
                      // e a mudança é apenas uma correção, então
                      // prosseguimos com a modificação
                      $changedEnd = true;
                    } else {
                      throw new Exception("O rastreador encontra-se "
                        . "vinculado a outro veículo"
                      );
                    }
                  } else {
                    throw new Exception("O rastreador encontra-se "
                      . "desvinculado"
                    );
                  }
                } else {
                  $changedEnd = $uninstalledAt->notEqualTo(
                    $installationRecord->uninstalledat
                  );
                }
              }

              if ($changedStart || $changedEnd) {
                // Modificamos o registro de instalação

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe nem do equipamento ou veículo
                unset($recordData['installationid']);
                unset($recordData['equipmentid']);
                unset($recordData['vehicleid']);
                
                // Grava as informações do registro de instalação
                $record = InstallationRecord::findOrFail($recordID);

                $record->installedat = $installedAt;
                $record->uninstalledat = $uninstalledAt;
                $record->updatedbyuserid = $userID;
                $record->save();

              }
            }

            // ---------------------------------------[ Subsídios ]-----

            // Primeiro apagamos os subsídios removidos pelo usuário
            // durante a edição
            foreach ($delSubsidies AS $subsidyID) {
              // Apaga cada subsídio
              $subsidy = Subsidy::findOrFail($subsidyID);
              $subsidy->delete();
            }

            // Agora inserimos os novos subsídios
            foreach ($newSubsidies AS $subsidyData) {
              // Incluímos um novo subsídio nesta instalação
              unset($subsidyData['subsidyid']);
              $subsidy = new Subsidy();
              $subsidy->fill($subsidyData);
              $subsidy->contractorid    = $contractor->id;
              $subsidy->installationid  = $installationID;
              $subsidy->createdbyuserid = $userID;
              $subsidy->updatedbyuserid = $userID;
              $subsidy->save();
            }

            // Por último, modificamos os subsídios mantidos
            foreach ($updSubsidies AS $subsidyData) {
              // Retira a ID do subsídio
              $subsidyID = $subsidyData['subsidyid'];
              unset($subsidyData['subsidyid']);

              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe
              unset($subsidyData['contractorid']);
              unset($subsidyData['installationid']);

              // Grava as informações do subsídio
              $subsidy = Subsidy::findOrFail($subsidyID);
              $subsidy->fill($subsidyData);
              $subsidy->updatedbyuserid = $userID;
              $subsidy->save();
            }

            // ---------------------------------------------------------

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Modificada a instalação '{installation}' do "
              . "contrato nº '{number}' do cliente '{customername}' no "
              . "contratante {contractor}.",
              [ 'installation' => $installation['installationnumber'],
                'number' => $installation['contractnumber'],
                'customername' => $installation['customername'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A instalação <i>'{installation}'</i>"
              . " do contrato nº <i>'{number}'</i> do cliente "
              . "<i>{customername}<i> foi modificada com sucesso.",
              [ 'installation' => $installation['installationnumber'],
                'number' => $installation['contractnumber'],
                'customername' => $installation['customername'] ]
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
          $this->error("Não foi possível modificar as informações da "
            . "instalação '{installation}' do  contrato nº '{number}' "
            . "do cliente '{customername}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}",
            [ 'installation' => $installation['installationnumber'],
              'number' => $installation['contractnumber'],
              'customername' => $installation['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da instalação. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações da "
            . "instalação '{installation}' do  contrato nº '{number}' "
            . "do cliente '{customername}' no contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'installation' => $installation['installationnumber'],
              'number' => $installation['contractnumber'],
              'customername' => $installation['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da instalação. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados da instalação são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($installation);
    }
    
    // Exibe um formulário para edição de uma instalação
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Contratos',
      $this->path('ERP\Financial\Contracts')
    );
    $this->breadcrumb->push('Instalações', '');
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Financial\Contracts\Installations\Edit', [
        'installationID' => $installationID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da instalação '{installation}' "
      . "do contrato '{number}' do cliente '{customername}' no "
      . "contratante {contractor}.",
      [ 'installation' => $installation['installationnumber'],
        'number' => $installation['contractnumber'],
        'customername' => $installation['customername'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/contracts/installations/installation.twig',
      [ 'formMethod' => 'PUT',
        'measureTypes' => $measureTypes ])
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
    $this->debug("Processando à remoção de instalação de contrato.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $installationID = $args['installationID'];

    try
    {
      // Recupera as informações do contrato
      $installation = Installation::where('contractorid',
            '=', $contractor->id
          )
        ->where('installationid', '=', $installationID)
        ->firstOrFail()
      ;
      
      // Agora apaga a instalação

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Agora apaga a instalação e os valores relacionados
      $installation->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("A instalação '{number}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'number' => $installation->installationnumber,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a instalação "
              . "{$installation->installationnumber}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a instalação "
        . "código {installationID} para remoção.",
        [ 'installationID' => $installationID ]
      );
      
      $message = "Não foi possível localizar a instalação para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "instalação ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $installationID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a instalação. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "instalação ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $installationID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a instalação. Erro "
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
