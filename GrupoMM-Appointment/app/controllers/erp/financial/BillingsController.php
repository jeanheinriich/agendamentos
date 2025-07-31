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
 * O controlador do gerenciamento de lançamentos (valores cobrados em
 * cada instalação).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\Billing;
use App\Models\Contract;
//use App\Models\Installation;
//use App\Models\RenegotiatedBilling;
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

class BillingsController
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
      'customername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do cliente'),
      'customerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 100)
        ->setName('Unidade/filial/titular responsável'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial/titular'),
      'contractid' => V::notBlank()
        ->intVal()
        ->setName('Nº do contrato'),
      'installationid' => V::notBlank()
        ->intVal()
        ->setName('Nº da instalação'),
      'name' => V::notBlank()
        ->length(1, 60)
        ->setName('Descrição do lançamento'),
      'contractchargeid' => V::intVal()
        ->setName('Nº do valor cobrado no contrato'),
      'installmenttypeid' => V::intVal()
        ->setName('Nº do parcelamento disponível'),
      'billingdate' => V::notEmpty()
        ->date('d/m/Y')
        ->setName('Data do lançamento'),
      'value' => V::numericValue()
        ->minimumValue('0,00')
        ->setName('Valor cobrado'),
      'numberofinstallments' => V::intVal()
        ->setName('Quantidade de parcelas'),
      'installmentvalue' => V::numericValue()
        ->minimumValue('0,00')
        ->setName('Valor da parcela')
    ];

    if ($addition) {
      // Ajusta as regras para adição de um novo lançamento
      
      // Retiramos as regras para campos que não fazem parte desta parte
      // da edição
      unset($validationRules['billingid']);
    } else {
      unset($validationRules['installmenttypeid']);
      unset($validationRules['installmentvalue']);
      $validationRules['contractnumber'] = V::notEmpty()
        ->setName('Número do contrato');
      $validationRules['installationnumber'] = V::notEmpty()
        ->setName('Número da instalação');
      $validationRules['installmentnumber'] = V::intval()
        ->setName('Número da parcela');
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de contratos.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter os contratos
   *   disponíveis
   * @param int $subsidiaryID
   *   A ID da unidade/filial do cliente para o qual desejamos obter os
   *   contratos disponíveis
   *
   * @return Collection
   *   A matriz com as informações de contratos
   *
   * @throws RuntimeException
   *   Em caso de não termos contratos
   */
  protected function getContracts(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    try {
      // Recupera as informações de contratos deste cliente
      $contracts = Contract::where('customerid', '=', $customerID)
        ->where('subsidiaryid', '=', $subsidiaryID)
        ->get([
            "contractid AS id",
            $this->DB->raw('getContractNumber(createdat) AS number'),
            $this->DB->raw("trim(to_char(monthprice, '9999999999D99')) AS monthprice"),
            $this->DB->raw(''
              . "CASE"
              . "  WHEN signaturedate IS NULL THEN 'Não assinado'"
              . "  ELSE 'Assinado em ' || to_char(signaturedate, 'DD/MM/YYYY') "
              . "END AS description")
          ])
      ;

      if ( $contracts->isEmpty() ) {
        throw new Exception("Não temos nenhum contrato cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de contratos. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os contratos");
    }

    return $contracts;
  }

  /**
   * Recupera as informações de instalações de um contrato.
   *
   * @param int $contractID
   *   A ID do contrato para o qual desejamos obter as instalações
   *   disponíveis
   *
   * @return array
   *   A matriz com as informações de instalações
   *
   * @throws RuntimeException
   *   Em caso de não termos instalações
   */
  protected function getInstallations(
    int $contractID
  ): array
  {
    try {
      // Recupera as informações de instalações, incluindo instalações
      // que estejam encerradas e/ou de contratos suspensos
      $sql = "SELECT installationID AS id,
                     installationNumber,
                     plate,
                     to_char(startDate, 'DD/MM/YYYY') AS startDate,
                     noTracker
                FROM erp.getInstallationsData({$contractID}, TRUE, TRUE, 0);"
      ;
      $installationsData = $this->DB->select($sql);
      $installations = [];
      
      foreach ($installationsData AS $installation) {
        if ($installation->startdate) {
          if ($installation->notracker) {
            $description = 'Sem rastreador';
          } else {
            $description = 'Instalado em ' . $installation->startdate;
          }
        } else {
          $description = 'Não instalado';
        }

        $installations[] = [
          'id' => $installation->id,
          'number' => $installation->installationnumber,
          'plate' => $installation->plate,
          'description' => $description
        ];
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "instalações. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as instalações");
    }

    return $installations;
  }

  /**
   * Exibe a página inicial do gerenciamento de lançamentos.
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
    $this->breadcrumb->push('Lançamentos',
      $this->path('ERP\Financial\Billings')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de lançamentos.");
    
    // Recupera os dados da sessão
    $billing = $this->session->get('billing',
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
      'erp/financial/billings/billings.twig',
      [ 'billing' => $billing ])
    ;
  }
  
  /**
   * Recupera a relação dos valores lançados em cada instalação em
   * formato JSON.
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
    $this->debug("Acesso à relação de lançamentos por instalação.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = (array) $request->getParsedBody();

    // Recupera o ID do contratante
    $contractorID = $this->authorization->getContractor()->id;
    
    if (isset($postParams['request'])) {
      // Obtemos a ID da instalação
      $installationID   = $postParams['installationID'];
      if (array_key_exists('toBePerformedService', $postParams)) {
        // Recuperamos os dados adicionais do período a ser cobrado
        $startDate       = $this->toSQLDate($postParams['startDate']);
        $numberOfParcels = $postParams['numberOfParcels'];

        // Recuperamos os valores dos serviços de período futuro, de
        // forma a permitir a cobrança antecipada
        $sql = "SELECT referenceMonthYear,
                       startDateOfPeriod,
                       endDateOfPeriod,
                       name,
                       value
                  FROM erp.toBePerformedService({$installationID},
                         '{$startDate}', {$numberOfParcels})
                 ORDER BY startDateOfPeriod;"
        ;
        $billings = $this->DB->select($sql);
        if (count($billings) > 0) {
          $billings = json_decode(json_encode($billings), true);
        }
      } else {
        // Analisamos o que precisa ser obtido
        $contractID       = $postParams['contractID'];
        $getOpenValues    = array_key_exists('onlyTariffs', $postParams)
          ? (($postParams['onlyTariffs'] == 'true')?false:true)
          : false
        ;
        $getChargeValues  = array_key_exists('withTariffs', $postParams)
          ? (($postParams['withTariffs'] == 'true')?true:false)
          : false
        ;
        $getAppuredValues = array_key_exists('withAppuredValues', $postParams)
          ? (($postParams['withAppuredValues'] == 'true')?true:false)
          : false
        ;

        $billings = [];
        if ($getOpenValues) {
          // Recuperamos as informações de valores em abertos na instalação
          // informada
          $sql = "SELECT B.billingID AS id,
                         B.contractID,
                         B.billingDate,
                         B.name,
                         B.billingValue,
                         B.installmentNumber,
                         B.numberOfInstallments,
                         false AS contractCharge,
                         true AS ratePerEquipment,
                         false AS isAscertained
                    FROM erp.getBillingsData({$contractorID}, NULL,
                      NULL, NULL, '{$installationID}', 'installationid',
                      FALSE, 'billings.billingdate', 0, 0) AS B
                   WHERE B.granted = FALSE
                     AND B.renegotiated = FALSE;"
          ;
          $billings = $this->DB->select($sql);
          if (count($billings) > 0) {
            $billings = json_decode(json_encode($billings), true);
          }
        }

        $chargeValues = [];
        if ($getChargeValues) {
          // Recuperamos quaisquer outros valores de cobranças mensais
          // presentes no contrato e que precisam ser computados
          $sql = "SELECT C.contractChargeID AS ID,
                         C.contractID,
                         CURRENT_DATE AS billingDate,
                         B.name,
                         C.chargeValue AS billingValue,
                         0 AS installmentNumber,
                         0 AS numberOfInstallments,
                         true AS contractCharge,
                         B.ratePerEquipment,
                         false AS isAscertained
                    FROM erp.contractCharges AS C
                   INNER JOIN erp.billingTypes AS B USING (billingTypeID)
                   WHERE C.contractID = {$contractID}
                     AND B.billingMoments @> array[5]
                     AND B.inAttendance = false"
          ;
          $chargeValues = $this->DB->select($sql);
          if (count($chargeValues) > 0) {
            $chargeValues = json_decode(json_encode($chargeValues), true);
          }
        }

        $appuredValues = [];
        $terminationValues = [];
        if ($getAppuredValues) {
          // Recuperamos valores apurados desta instalação até hoje
          $sql = "SELECT 0 AS ID,
                         {$contractID} AS contractID,
                         CURRENT_DATE AS billingDate,
                         'Mensalidade de ' || to_char(startdate, 'DD/MM/YYYY') || ' à ' || to_char(enddate, 'DD/MM/YYYY') AS name,
                         finalvalue AS billingValue,
                         0 AS installmentNumber,
                         0 AS numberOfInstallments,
                         true AS contractCharge,
                         true AS ratePerEquipment,
                         true AS isascertained
                    FROM jsonb_to_record(
                           erp.performedServiceUntilToday({$installationID})
                         ) AS performedserviceuntiltoday(startdate  date, enddate date, monthprice numeric(12,2), grossvalue numeric(12,2), discountvalue numeric(12,2), finalvalue numeric(12,2), ascertaineddays int)
                   WHERE finalValue IS NOT NULL;";
          $appuredValues = $this->DB->select($sql);
          if (count($appuredValues) > 0) {
            $appuredValues = json_decode(json_encode($appuredValues), true);
          }

          // Recuperamos valores de multa por quebra de fidelidade e de
          // valores de encerramento desta instalação até hoje
          $sql = "SELECT 0 AS ID,
                         {$contractID} AS contractID,
                         CURRENT_DATE AS billingDate,
                         name,
                         value AS billingValue,
                         0 AS installmentNumber,
                         0 AS numberOfInstallments,
                         true AS contractCharge,
                         true AS ratePerEquipment,
                         true AS isascertained
                    FROM erp.contractTerminationValues({$installationID},
                         date_trunc('month', CURRENT_DATE)::DATE,
                         CURRENT_DATE);";
          $terminationValues = $this->DB->select($sql);
          if (count($terminationValues) > 0) {
            $terminationValues = json_decode(json_encode($terminationValues), true);
          }
        }

        $billings = array_merge($billings, $chargeValues, $appuredValues, $terminationValues);
      }

      if (count($billings) > 0) {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Dados de lançamentos para a instalação "
                . "cuja ID é '{$installationID}'",
              'data' => $billings
            ])
        ;
      }
      $this->debug('Sem dados');

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => "Não temos dados de lançamentos para a "
              . "instalação cuja ID é '{$installationID}'",
            'data' => NULL
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
    $searchValue  = $postParams['searchValue'];
    $customerID   = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('billing',
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

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido dos campos de pesquisa
      $customerID = $customerID?$customerID:0;

      // Realiza a consulta
      $sql = "SELECT B.billingID AS id,
                     B.customerID,
                     B.customerName,
                     B.subsidiaryID,
                     B.subsidiaryName,
                     B.contractID,
                     B.contractNumber,
                     B.planID,
                     B.planName,
                     B.dueDay,
                     B.installationID,
                     B.installationNumber,
                     B.vehicleID,
                     B.plate,
                     to_char(B.billingDate, 'DD/MM/YYYY') AS billingDate,
                     B.name,
                     B.billingValue,
                     B.installmentNumber,
                     B.numberOfInstallments,
                     B.granted,
                     B.reasonforgranting,
                     B.renegotiated,
                     B.renegotiationID,
                     B.inMonthlyCalculation,
                     B.fullcount
                FROM erp.getBillingsData({$contractorID}, {$customerID},
                  {$subsidiaryID}, NULL, '{$searchValue}', '{$searchField}',
                  FALSE, '{$ORDER}', {$start}, {$length}) AS B;"
      ;
      $billings = $this->DB->select($sql);

      if (count($billings) > 0) {
        $rowCount = $billings[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $billings
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos lançamentos cadastrados.";
        } else {
          switch ($searchField) {
            case 'contractNumber':
              $error = "Não temos lançamentos cadastrados cujo número do "
                . "contrato contém <i>{$searchValue}</i>."
              ;
              
              break;
            case 'installationNumber':
              $error = "Não temos lançamentos cadastrados que possuam "
                . "uma instalação cujo número contém "
                . "<i>{$searchValue}</i>."
              ;
              
              break;
            default:
              $error = "Não temos lançamentos cadastrados cujas "
                . "instalações consta o veículo placa "
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
        [ 'module' => 'lançamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "lançamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'lançamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "lançamentos. Erro interno."
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
   * Exibe um formulário para adição de um novo lançamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    $contracts = [];
    $installations = [];

    // Recupera as informações de parâmetros adicionais informados
    $parms = $request->getQueryParams();
    if (count($parms) > 0) {
      $customerID         = $parms['customerID'];
      $customerName       = $parms['customerName'];
      $subsidiaryID       = $parms['subsidiaryID'];
      $subsidiaryName     = $parms['subsidiaryName'];
      $contractID         = $parms['contractID'];
      $contractNumber     = $parms['contractNumber'];
      $installationID     = $parms['installationID'];
      $installationNumber = $parms['installationNumber'];

      // Recupera as informações dos contratos
      $contracts = $this->getContracts($customerID, $subsidiaryID);

      // Recupera as informações das instalações do contrato
      $installations = $this->getInstallations($contractID);
    } else {
      $customerID = 0;
      $customerName = '';
      $subsidiaryID = 0;
      $subsidiaryName = '';
      $contractID = 0;
      $contractNumber = '';
      $installationID = 0;
      $installationNumber = '';
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de lançamento.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do lançamento são VÁLIDOS');

        // Recupera os dados do lançamento
        $billingData  = $this->validator->getValues();
        $customerID   = $billingData['customerid'];
        $subsidiaryID = $billingData['subsidiaryid'];
        $contractID   = $billingData['contractid'];

        try
        {
          // Grava a novo lançamento

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Recuperamos qual o ID da fatura aberta para este cliente
          // nesta instalação
          $invoices = Billing::join('invoices', 'billings.invoiceid',
                '=', 'invoices.invoiceid'
              )
            ->where('invoices.underanalysis', 'true')
            ->where('billings.installationid', '=', $billingData['installationid'])
            ->get([
                'invoices.invoiceid',
                'invoices.referencemonthyear'
              ])
          ;

          if ( $invoices->isEmpty() ) {
            // Não têm nenhum faturamento em processamento
            $invoiceID = 0;
          } else {
            $invoice = $invoices->first();
            $invoiceID = $invoice->invoiceid;
            $periodStart = Carbon::createFromFormat('d/m/Y',
              '01/'. $invoice->referencemonthyear
            );
            $periodEnd = $periodStart
              ->copy()
              ->endOfMonth()
            ;
          }
          
          if ($billingData['numberofinstallments'] > 1) {
            $this->debug("A instalação possui parcelamento");
            // Precisamos adicionar as parcelas deste lançamento
            $numberofinstallments = $billingData['numberofinstallments'];
            $installmentnumber = 1;
            $installmentvalue = $billingData['installmentvalue'];
            $installmentvalue = floatval(
              str_replace(',', '.',
                str_replace('.', '', $installmentvalue)
              )
            );
            
            // Incrementa a data de cada parcela
            $billingDate = Carbon::createFromFormat('d/m/Y',
              $billingData['billingdate']
            );

            do {
              $billing = new Billing();
              $billing->fill($billingData);
              // Substituímos o valor da parcela
              $billing->billingdate = $billingDate->format('Y-m-d');
              $billing->installmentnumber = $installmentnumber;
              $billing->numberofinstallments = $numberofinstallments;
              $billing->value = $installmentvalue;
              if ($invoiceID > 0) {
                // Analisa se a cobrança deve ser lançada na fatura em
                // aberto
                if ($billingDate->lessThanOrEqualTo($periodEnd)) {
                  // Adiciona a fatura da qual faz parte
                  $billing->invoiceid = $invoiceID;
                }
              }

              // Adiciona o contratante e usuário responsável pelo registro
              $billing->contractorid = $contractor->id;
              $billing->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $billing->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $billing->save();

              $installmentnumber++;
              $billingDate->addMonths(1);
            } while ($installmentnumber <= $numberofinstallments);
          } else {
            $billing = new Billing();
            unset($billingData['installmentnumber']);
            unset($billingData['numberofinstallments']);
            $billing->fill($billingData);

            if ($invoiceID > 0) {
              // Determina a data da parcela
              $billingDate = Carbon::createFromFormat('d/m/Y',
                $billingData['billingdate']
              );

              // Analisa se a cobrança deve ser lançada na fatura em
              // aberto
              if ($billingDate->lessThanOrEqualTo($periodEnd)) {
                // Adiciona a fatura da qual faz parte
                $billing->invoiceid = $invoiceID;
              }
            }

            // Adiciona o contratante e usuário responsável pelo registro
            $billing->contractorid = $contractor->id;
            $billing->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billing->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billing->save();
          }

          if ($invoiceID > 0) {
            // Força o recalculamento da fatura
            $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
              . "{$invoiceID});"
            ;
            $this->DB->select($sql);
          }

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Cadastrado o lançamento '{name}' na instalação "
            . "'{installation}' do cliente '{customerName}' no "
            . "contratante '{contractor}' com sucesso.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O lançamento '{name}' foi cadastrado "
            . "com sucesso.",
            [ 'name'  => $billingData['name'] ]
          );
          
          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Financial\Billings' ]
          );
          
          // Redireciona para a página de gerenciamento de lançamentos
          return $this->redirect($response,
            'ERP\Financial\Billings')
          ;
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do lançamento. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "lançamento '{name}' na instalação  '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do lançamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do lançamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Recupera os dados do lançamento
        $billingData = $this->validator->getValues();

        if ($billingData['customerid'] > 0) {
          $customerID   = $billingData['customerid'];
          $subsidiaryID = $billingData['subsidiaryid'];
          $contractID   = $billingData['contractid'];
        }
      }
    } else {
      // Carrega um conjunto de valores vazios
      $emptyBilling = [
        'customerid' => $customerID,
        'customername' => $customerName,
        'subsidiaryid' => $subsidiaryID,
        'subsidiaryname' => $subsidiaryName,
        'contractid' => $contractID,
        'contractnumber' => $contractNumber,
        'installationid' => $installationID,
        'installationnumber' => $installationNumber,
        'installmenttypeid' => 0,
        'billingdate' => Carbon::now()->format('d/m/Y'),
        'value' => '0,00',
        'numberofinstallments' => 0,
        'installmentvalue' => '0,00'
      ];
      $this->validator->setValues($emptyBilling);
    }

    try {
      if (($customerID > 0) && ($subsidiaryID > 0)) {
        // Recupera as informações dos contratos deste cliente
        $contracts = $this->getContracts($customerID, $subsidiaryID);
        $contracts = $contracts
          ->toArray()
        ;
      }

      if ($contractID > 0) {
        // Recupera as informações das instalações do contrato
        $installations = $this->getInstallations($contractID);
      }
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Billings' ]
      );

      // Redireciona para a página de gerenciamento de lançamentos
      return $this->redirect($response, 'ERP\Financial\Billings');
    }
    
    // Exibe um formulário para adição de um lançamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Lançamentos',
      $this->path('ERP\Financial\Billings')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Financial\Billings\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de lançamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/billings/newbilling.twig',
      [
        'formMethod' => 'POST',
        'contracts' => $contracts,
        'installations' => $installations
      ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um lançamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    try
    {
      // Recupera as informações do lançamento
      $billingID = $args['billingID'];
      $billing = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customers',
            'contracts.customerid', '=', 'customers.entityid'
          )
        ->join('subsidiaries',
            'contracts.subsidiaryid', '=', 'subsidiaries.subsidiaryid'
          )
        ->join('installations', 'billings.installationid',
            '=', 'installations.installationid'
          )
        ->join('users AS createduser',
            'billings.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'billings.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('billings.contractorid', '=', $contractor->id)
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.*',
            $this->DB->raw('CASE WHEN billings.contractchargeid IS NULL THEN 0 ELSE billings.contractchargeid END AS contractchargeid'),
            $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
            'contracts.customerid',
            'contracts.subsidiaryid',
            'customers.name AS customername',
            'subsidiaries.name AS subsidiaryname',
            'installations.installationnumber',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $billing->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum lançamento "
          . "com o código {$billingID} cadastrado"
        );
      }
      $billing = $billing
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID}.",
        [ 'billingID' => $billingID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "lançamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Billings' ]
      );
      
      // Redireciona para a página de gerenciamento de lançamentos
      return $this->redirect($response,
        'ERP\Financial\Billings'
      );
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do lançamento '{name}' do "
        . "cliente '{customername}' no contratante {contractor}.",
        [ 'name' => $billing['name'],
          'customername' => $billing['customername'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do lançamento são VÁLIDOS');

        // Grava as informações no banco de dados

        // Recupera os dados modificados do lançamento
        $billingData = $this->validator->getValues();

        try
        {
          // Não permite modificar o contratante
          unset($billingData['contractorid']);

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Recuperamos qual o ID da fatura aberta para este cliente
          // nesta instalação
          $invoices = Billing::join('invoices', 'billings.invoiceid',
                '=', 'invoices.invoiceid'
              )
            ->where('invoices.underanalysis', 'true')
            ->where('billings.installationid', '=', $billingData['installationid'])
            ->get([
                'invoices.invoiceid',
                'invoices.referencemonthyear'
              ])
          ;

          if ( $invoices->isEmpty() ) {
            // Não têm nenhum faturamento em processamento
            $invoiceID = 0;
          } else {
            $invoice = $invoices->first();
            $invoiceID = $invoice->invoiceid;
            $periodStart = Carbon::createFromFormat('d/m/Y',
              '01/'. $invoice->referencemonthyear
            );
            $periodEnd = $periodStart
              ->copy()
              ->endOfMonth()
            ;
          }

          // Recupera a data da cobrança
          $billingDate = Carbon::createFromFormat('d/m/Y',
            $billingData['billingdate']
          );

          if ($invoiceID > 0) {
            $inInvoice = 'NULL';
            if ($billingDate->lessThanOrEqualTo($periodEnd)) {
              // Adiciona a fatura da qual faz parte
              $inInvoice = (string) $invoiceID;
            }

            $invoiceData = sprintf("invoiceID = %s, ", $inInvoice);
          } else {
            $invoiceData = '';
          }

          // Fazemos a modificação desta fatura
          $sql = sprintf(""
            . "UPDATE erp.billings "
            .    "SET name = '%s', "
            .        "value = %.2f, "
            .        "billingDate = '%s'::Date, "
            .        $invoiceData
            .        "updatedAt = CURRENT_TIMESTAMP, "
            .        "updatedByUserID = %d "
            .  "WHERE billingID = %d;",
            $billingData['name'],
            $this->toFloat($billingData['value']),
            $billingDate->format('Y-m-d'),
            $this->authorization->getUser()->userid,
            $billingID
          );
          $this->DB->select($sql);

          if ($invoiceID > 0) {
            // Força o recalculamento da fatura
            $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
              . "{$invoiceID});"
            ;
            $this->DB->select($sql);
          }

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Modificado o lançamento '{name}' na instalação "
            . "'{installation}' do cliente '{customerName}' no "
            . "contratante '{contractor}' com sucesso.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName' => $billingData['customername'],
              'contractor' => $contractor->name ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O lançamento '{name}' foi modificado "
            . "com sucesso.",
            [ 'name'  => $billingData['name'] ]
          );
          
          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Financial\Billings' ]
          );
          
          // Redireciona para a página de gerenciamento de lançamentos
          return $this->redirect($response,
            'ERP\Financial\Billings'
          );
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName'  => $billingData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName'  => $billingData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do lançamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($billing);
    }
    
    // Exibe um formulário para edição de um lançamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Lançamentos',
      $this->path('ERP\Financial\Billings')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Financial\Billings\Edit', [
        'billingID' => $billingID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do lançamento '{name}' do cliente "
      . "'{customername}' no contratante {contractor}.",
      [ 'name' => $billing['name'],
        'customername' => $billing['customername'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/billings/billing.twig',
      [ 'formMethod' => 'PUT',
        'contracts' => [],
        'installations' => [] ])
    ;
  }
  
  /**
   * Remove o lançamento.
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
    $this->debug("Processando à remoção de lançamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $billingID = $args['billingID'];

    try
    {
      // Recupera as informações do lançamento
      $billingData = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customer',
            'contracts.customerid', '=', 'customer.entityid'
          )
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.billingid AS id',
            'customer.name AS customername'
          ])
        ->first()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID} para remoção.",
        [ 'billingID' => $billingID ]
      );
    
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => 'Não foi possível localizar o lançamento para remoção',
            'data' => null
          ])
      ;
    }

    try
    {
      // Iniciamos a transação
      $this->DB->beginTransaction();

      $billing = Billing::where('contractorid',
            '=', $contractor->id
          )
        ->where('billingid', '=', $billingID)
        ->firstOrFail()
      ;
      
      // Agora apaga o lançamento do valor a ser cobrado
      $billing->delete();

      // Força o recalculamento da fatura
      $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
        . "{$billingData->invoiceid});"
      ;
      $this->DB->select($sql);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O lançamento '{name}' do cliente '{customername}' "
        . "do contratante '{contractor}' foi removido com sucesso.",
        [ 'name' => $billingData->name,
          'customername' => $billingData->customername,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o lançamento "
              . "{$billingData->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o lançamento. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o lançamento. Erro "
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
   * Abona o lançamento.
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
  public function grante(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando o abono de lançamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $billingID = $args['billingID'];

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera o motivo do abono
    $reasonForGranting = $postParams['reasonForGranting'];

    try
    {
      // Recupera as informações do lançamento
      $billingData = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customer',
            'contracts.customerid', '=', 'customer.entityid'
          )
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.billingid AS id',
            'customer.name AS customername',
            'billings.invoiceid',
            'billings.granted'
          ])
        ->first()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID}.",
        [ 'billingID' => $billingID ]
      );
    
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => 'Não foi possível localizar o lançamento para abonar',
            'data' => null
          ])
      ;
    }

    try
    {
      if ($billingData->granted === false) {
        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Abona o valor cobrado
        $billing = Billing::where('contractorid',
              '=', $contractor->id
            )
          ->where('billingid', '=', $billingID)
          ->firstOrFail()
        ;
        $billing->granted = "true";
        $billing->reasonforgranting = $reasonForGranting;

        // Adicionamos as informações do responsável pelo abono
        $billing->updatedbyuserid =
          $this->authorization->getUser()->userid
        ;
        $billing->save();

        if ($billingData->invoiceid > 0) {
          // Força o recalculamento da fatura
          $invoiceID = $billingData->invoiceid;
          $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
            . "{$invoiceID});"
          ;
          $this->DB->select($sql);
        }

        // Efetiva a transação
        $this->DB->commit();

        // Registra o sucesso
        $this->info("O lançamento '{name}' do cliente '{customername}' "
          . "do contratante '{contractor}' foi abonado com sucesso.",
          [ 'name' => $billingData->name,
            'customername' => $billingData->customername,
            'contractor' => $contractor->name ]
        );
      } else {
        $this->info("O lançamento '{name}' do cliente '{customername}' "
          . "do contratante '{contractor}' já estava abonado.",
          [ 'name' => $billingData->name,
            'customername' => $billingData->customername,
            'contractor' => $contractor->name ]
        );
      }
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Abonado o lançamento "
              . "{$billingData->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível abonar as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível abonar o lançamento. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível abonar as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível abonar o lançamento. Erro "
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
