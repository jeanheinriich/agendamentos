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
 * O controlador do gerenciamento de cobranças e pagamentos, permitindo
 * visualizar as cobranças pendentes, emitir cobranças avulsas e/ou
 * carnês, dar baixa em valores pagos, analisar os pagamentos de um
 * cliente, bem como renegociar valores em aberto.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\AscertainedPeriod;
use App\Models\AscertainedPeriodDetail;
use App\Models\BankingBilletPayment;
use App\Models\BankingBilletOccurrence;
use App\Models\Entity as Contractor;
use App\Models\EmailQueue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCondition;
use App\Models\PaymentMethod;
use App\Models\PaymentSituation AS PaymentSituationModel;
use Carbon\Carbon;
use Core\Codec\URLCodec;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\FileHandlers\Excel_XML;
use Core\Helpers\FormatterTrait;
use Core\Mailer\MailerTrait;
use Core\Payments\AgentEntity;
use Core\Payments\BankingBillet\BankingBilletFactory;
use Core\Payments\BankingBillet\Renderer\HTML;
use Core\Payments\Cnab\BilletStatus;
use Core\Payments\PaymentSituation;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use NumberFormatter;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class PaymentsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o envio de e-mails
   */
  use MailerTrait;

  /**
   * As funções de formatação especiais
   */
  use FormatterTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos.
   */
  use HandleFileTrait;

  /**
   * Os eventos para um e-mail.
   */
  public const BILLET_SUBMISSION = 1;
  public const EXPIRATION_NOTICE = 2;
  public const OVERDUE_NOTICE = 3;
  public const PAYMENT_RECEIPT_SUBMISSION = 4;

  /**
   * Recupera as informações de meios de pagamento.
   *
   * @return Collection
   *   A matriz com as informações de meios de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos meios de pagamento
   */
  protected function getPaymentMethods(): Collection
  {
    try {
      // Recupera as informações de meios de pagamento
      $paymentMethods = PaymentMethod::orderBy('paymentmethodid')
        ->get([
            'paymentmethodid AS id',
            'name'
          ])
      ;

      if ( $paymentMethods->isEmpty() ) {
        throw new Exception("Não temos nenhum meio de pagamento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de meios de "
        . "pagamento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      $paymentMethods = [];
    }

    return $paymentMethods;
  }

  /**
   * Recupera as informações de situações de um pagamento.
   *
   * @return Collection
   *   A matriz com as informações de situações de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos situações de pagamento
   */
  protected function getPaymentSituations(): Collection
  {
    try {
      // Recupera as informações de situações do pagamento
      $paymentSituations = PaymentSituationModel::orderBy('paymentsituationid')
        ->get([
            'paymentsituationid AS id',
            'name'
          ])
      ;

      if ( $paymentSituations->isEmpty() ) {
        throw new Exception("Não temos nenhuma situação de pagamento "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de situações "
        . "de pagamento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      $paymentSituations = [];
    }

    return $paymentSituations;
  }

  /**
   * Recupera as informações dos meios de pagamento.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter as condições de
   * pagamento disponíveis
   * @param bool $atSight
   *   O indicativo de que devemos obter apenas pagamentos À vista
   * 
   * @return Collection
   *   A matriz com as informações de condições de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos condições de pagamento cadastrados
   */
  protected function getPaymentConditions(
    int $contractorID,
    bool $atSight = false
  ): Collection {
    try {
      // Recupera as informações de condições de pagamento deste
      // contratante
      $paymentQry = PaymentCondition::where("contractorid", '=',
            $contractorID
          )
        ->where("blocked", "false");

      if ($atSight) {
        $paymentQry
          ->where('timeunit', '=', 'DAY')
          ->whereRaw("array_length(string_to_array(paymentinterval, '/'), 1) = 1")
        ;
      }

      $paymentConditions = $paymentQry
        ->get([
          'paymentconditionid as id',
          'name',
          'paymentmethodid',
          $this->DB->raw(""
            . "CASE "
            . "  WHEN definedmethodid IS NULL THEN 0 "
            . "  ELSE definedmethodid "
            . "END AS definedmethodid"
          ),
          'timeunit',
          $this->DB->raw("string_to_array(paymentinterval, '/') AS paymentinterval")
        ])
      ;

      if ($paymentConditions->isEmpty()) {
        throw new Exception("Não temos nenhuma condição de pagamento "
          . "cadastrada"
        );
      }
    } catch (Exception $exception) {
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
   * Exibe a página inicial do gerenciamento de cobranças.
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
    $this->breadcrumb->push(
      'Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push(
      'Cobranças',
      $this->path('ERP\Financial\Payments')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de cobranças e pagamentos.");

    // Recupera as informações de meios de pagamento
    $paymentMethods = $this->getPaymentMethods();

    // Recupera as informações de situações do pagamento
    $paymentSituations = $this->getPaymentSituations();

    // Recupera os dados da sessão
    $payment = $this->session->get(
      'payment',
      [
        'searchField' => 'date',
        'searchValue' => '',
        'methodID' => 0,
        'situationID' => 1,
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
      'erp/financial/payments/payments.twig',
      [
        'payment' => $payment,
        'paymentMethods' => $paymentMethods,
        'paymentSituations' => $paymentSituations
      ]
    );
  }

  /**
   * Recupera a relação dos valores de cobranças e pagamentos em formato
   * JSON.
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
    $this->debug("Acesso à relação de cobranças e pagamentos.");

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
    $searchField  = $postParams['searchField'];
    $searchValue  = $postParams['searchValue'];
    $methodID     = $postParams['methodID'];
    $situationID  = $postParams['situationID'];
    $customerID   = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;

    // Seta os valores da última pesquisa na sessão
    $this->session->set(
      'payment',
      [
        'searchField' => $searchField,
        'searchValue' => $searchValue,
        'methodID' => $methodID,
        'situationID' => $situationID,
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

    try {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido dos campos de pesquisa
      $customerID = $customerID ? $customerID : 0;

      // Realiza a consulta
      $contractorID = $this->authorization->getContractor()->id;

      $sql = "SELECT P.paymentID AS id,
                     P.invoiceID,
                     P.customerID,
                     P.customerName,
                     P.subsidiaryID,
                     P.subsidiaryName,
                     P.referenceMonthYear,
                     P.dueDate,
                     to_char(P.dueDate, 'DD/MM/YYYY') AS dueDate,
                     P.valueToPay,
                     P.overdue,
                     P.paymentMethodID,
                     P.paymentMethodName,
                     P.paymentSituationID,
                     P.restrictionID,
                     P.paymentSituationName,
                     P.droppedTypeID,
                     P.droppedTypeName,
                     to_char(P.paidDate, 'DD/MM/YYYY') AS paidDate,
                     P.paidValue,
                     P.billingCounter,
                     P.latePaymentInterest,
                     P.fineValue,
                     P.abatementValue,
                     P.tariffValue,
                     to_char(P.creditDate, 'DD/MM/YYYY') AS creditDate,
                     P.sentMailStatus,
                     P.hasError,
                     P.reasonForError,
                     P.fullcount
                FROM erp.getPaymentsData({$contractorID}, {$customerID},
                  {$subsidiaryID}, '{$searchValue}', '{$searchField}',
                  {$methodID}, {$situationID}, '{$ORDER}', {$start},
                  {$length}) AS P;"
      ;
      $payments = $this->DB->select($sql);

      if (count($payments) > 0) {
        $rowCount = $payments[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $payments
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos lançamentos cadastrados.";
        } else {
          switch ($searchField) {
            case 'invoiceid':
              $error = "Não temos cobranças cujo número do documento "
                . "seja <i>{$searchValue}</i>."
              ;

              break;
            case 'name':
              $error = "Não temos cobranças cujo nome do cliente "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            default:
              $error = "Não temos cobranças que conste "
                . "<i>{$searchValue}</i>."
              ;

              break;
          }
        }
      }
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [
          'module' => 'lançamentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "lançamentos. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [
          'module' => 'lançamentos',
          'error'  => $exception->getMessage()
        ]
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
          'data' => [],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para adição de uma cobrança avulsa, quando
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

    $typeOfPayments = [
      'carnet' => [
        'name' => 'carnê de pagamentos',
        'desc' => 'São emitidos antecipadamente boletos com os valores '
          . 'referentes aos serviços ainda a serem prestados ao '
          . 'cliente. Também são inclusos, além dos valores referente à '
          . 'mensalidade, cobranças de outros valores que estejam '
          . 'presentes em contrato.'
      ],
      'prepayment' => [
        'name' => 'cobrança de valores antecipados',
        'desc' => 'É emitido uma cobrança única da soma dos valores '
          . 'referentes aos serviços ainda a serem prestados ao '
          . 'cliente no período indicado, permitindo o pagamento '
          . 'prévio. Também são inclusos, além dos valores referente à '
          . 'mensalidade, cobranças de outros valores que estejam '
          . 'presentes em contrato.'
      ],
      'unusual' => [
        'name' => 'cobrança extraordinária',
        'desc' => 'Cobrança de valores que tenham sido lançados e que '
          . 'estejam pendentes de cobrança nos itens de contrato '
          . 'selecionados. Permite adicionar novos valores que ainda '
          . 'não tinham sido lançados previamente.'
      ],
      'closing' => [
        'name' => 'cobrança de encerramento',
        'desc' => 'São calculados os valores dos serviços prestados '
          . 'até a data da emissão, bem como acrescido dos demais '
          . 'valores lançados e que estejam ainda pendentes de '
          . 'cobrança. Permite também adicionar valores que ainda não '
          . 'tinham sido lançados previamente.'
      ],
      'another' => [
        'name' => 'cobrança de valores avulsos',
        'desc' => 'Cobrança de outros valores, não relacionados com os '
          . 'itens de contrato do cliente.'
      ]
    ];

    // Recupera as informações de parâmetros adicionais informados
    $parms = $request->getQueryParams();
    if (array_key_exists('chosenPayment', $parms)) {
      $chosenPayment = $parms['chosenPayment'];
    }

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      $atSight = (in_array($chosenPayment, ['carnet', 'prepayment']))
        ? true
        : false
      ;
      $paymentConditions = $this->getPaymentConditions(
        $contractor->id, $atSight
      );
    } catch (RuntimeException $exception) {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Payments' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'ERP\Financial\Payments');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug(
        "Processando à emissão de {type}",
        ['type'=>$typeOfPayments[$chosenPayment]['name']]
      );

      // Recupera os dados da requisição
      $postParams = $request->getParsedBody();

      // Obtemos os valores comuns
      $userID = $this->authorization->getUser()->userid;
      $customerName = $postParams['customername'];
      $customerID = $postParams['customerid'];
      //$subsidiaryName = $postParams['subsidiaryname'];
      if ($chosenPayment === 'unusual') {
        $chargeFromAssociate = array_key_exists('chargeFromAssociate', $postParams)
          ? ($postParams['chargeFromAssociate'] === 'true')
          : false
        ;

        if ($chargeFromAssociate) {
          $associateID = (int) $postParams['associateid'];
          }
      } else {
        $chargeFromAssociate = false;
      }

      $subsidiaryID = $postParams['subsidiaryid'];
      $dueDate = Carbon::createFromFormat(
          'd/m/Y',
          $postParams['duedate']
        )->locale('pt_BR')
      ;
      $valueToPay = $this->toFloat($postParams['valuetopay']);

      if ($chosenPayment !== 'carnet') {
        // Obtemos as informações da condição de pagamento
        $paymentConditionID = $postParams['paymentconditionid'];
        $paymentMethodID = $postParams['paymentmethodid'];
        $definedMethodID = $postParams['definedmethodid'];
      }

      if (in_array($chosenPayment, ['carnet', 'prepayment'])) {
        $startDate = Carbon::createFromFormat(
            'd/m/Y',
            $postParams['startdate']
          )->locale('pt_BR')
        ;
        $installations = $this->toSQLArray(
          $postParams['installations']
        );
        $numberOfParcels = $postParams['numberofparcels'];
      }

      if (in_array($chosenPayment, ['unusual', 'closing', 'another'])) {
        $billings = $postParams['billings'];
        $numberOfInstallments = $postParams['numberofinstallments'];

        if ($numberOfInstallments > 1) {
          $installmentValue = $this->toFloat($postParams['installmentvalue']);
        } else {
          $installmentValue = $valueToPay;
        }
      }

      // Conforme o tipo de cobrança, realizamos a emissão da cobrança
      switch ($chosenPayment) {
        case 'carnet':
          // Emissão de carnê de pagamentos

          // São emitidos antecipadamente boletos com os valores
          // referentes aos serviços ainda a serem prestados ao cliente.
          // Também são inclusos, além dos valores referente à
          // mensalidade, cobranças de outros valores que estejam
          // presentes em contrato.

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Emitimos o carnê de pagamentos através das instruções
          // informadas
          $sql = "SELECT erp.createCarnet({$contractor->id},"
            . "{$customerID}, {$subsidiaryID}, "
            . "'" . $startDate->format('Y-m-d') . "'::Date, "
            . "{$numberOfParcels}, "
            . "'" . $dueDate->format('Y-m-d') . "'::Date, {$userID}, "
            . "'{$installations}') AS carnetID;"
          ;
          $carnet = $this->DB->select($sql);

          if ($carnet[0]->carnetid > 0) {
            // Os dados do período cobrado
            $carnetID = $carnet[0]->carnetid;
            $start = $startDate
              ->subMonth()
              ->format('m/Y')
            ;
            $end = $startDate
              ->addMonths($numberOfParcels)
              ->format('m/Y')
            ;

            $number = str_pad($carnetID, 8, '0', STR_PAD_LEFT);

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Emitido o carnê de pagamentos nº {number} "
              . "referente ao período de {start} à {end} para o cliente "
              . "{customername} do contratante '{contractor}' com "
              . "sucesso.",
              [
                'number' => $number,
                'start' => $start,
                'end' => $end,
                'customername' => $customerName,
                'contractor' => $contractor->name
              ]
            );

            // Alerta o usuário
            $this->flash("success", "O carnê foi emitido com sucesso.");
          } else {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível emitir o carnê do cliente "
              . "'{customerName}' no contratante '{contractor}'. "
              . "Nenhum período apurado.",
              [
                'customerName'  => $customerName,
                'contractor' => $contractor->name
              ]
            );

            // Alerta o usuário
            $this->flash("error", "Não foi possível emitir o carnê do "
              . "cliente {$customerName}. Nenhum valor a ser cobrado."
            );
          }

          break;
        case 'prepayment':
          // Emissão de cobrança de valores antecipados

          // É emitido uma cobrança única da soma dos valores referentes
          // aos serviços ainda a serem prestados ao cliente no período
          // indicado, permitindo o pagamento prévio. Também são
          // inclusos, além dos valores referente à mensalidade,
          // cobranças de outros valores que estejam presentes em
          // contrato. 

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Emitimos a cobrança de valores antecipados
          $sql = "SELECT erp.createPrepayment({$contractor->id},"
            . "{$customerID}, {$subsidiaryID}, "
            . "'" . $startDate->format('Y-m-d') . "'::Date, "
            . "{$numberOfParcels}, "
            . "'" . $dueDate->format('Y-m-d') . "'::Date, "
            . "{$valueToPay}, '{$installations}', "
            . "{$paymentConditionID}, {$paymentMethodID}, "
            . "{$definedMethodID}, {$userID}) AS prepaymentID;"
          ;
          $prepayment = $this->DB->select($sql);

          if ($prepayment[0]->prepaymentid > 0) {
            // Os dados do período cobrado
            $prepaymentID = $prepayment[0]->prepaymentid;
            $start = $startDate
              ->subMonth()
              ->format('m/Y')
            ;
            $end = $startDate
              ->addMonths($numberOfParcels)
              ->format('m/Y')
            ;

            $number = str_pad($prepaymentID, 8, '0', STR_PAD_LEFT);

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Emitida cobrança antecipada de valores nº "
              . "{number} e vencimento em {duedate} referente ao "
              . "período de {start} à {end} para o cliente "
              . "{customername} do contratante '{contractor}' com "
              . "sucesso.",
              [
                'number' => $number,
                'duedate' => $dueDate->format('d/m/Y'),
                'start' => $start,
                'end' => $end,
                'customername' => $customerName,
                'contractor' => $contractor->name
              ]
            );

            // Alerta o usuário
            $this->flash("success", "Emitida a cobrança nº {$prepaymentID} com sucesso.");
          } else {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível emitir a cobrança do cliente "
              . "'{customerName}' no contratante '{contractor}'. "
              . "Nenhum período apurado.",
              [
                'customerName'  => $customerName,
                'contractor' => $contractor->name
              ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível emitir a cobrança "
              . "do cliente {$customerName}. Nenhum valor a ser cobrado."
            );
          }

          break;
        case 'unusual':
          // Emissão de cobrança extraordinária

          // Cobrança de valores que tenham sido lançados e que estejam
          // pendentes de cobrança nos itens de contrato selecionados.
          // Permite adicionar novos valores que ainda não tinham sido
          // lançados previamente.
        case 'closing':
          // Emição de cobrança de encerramento

          // São calculados os valores dos serviços prestados até a data
          // da emissão, bem como acrescido dos demais valores lançados
          // e que estejam ainda pendentes de cobrança. Permite também
          // adicionar valores que ainda não tinham sido lançados
          // previamente. 
        case 'another':
          // Emição de cobrança de outros valores

          // São calculados relacionados outros valores, independentes
          // de instalação 

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Localizamos a informação do meio de pagamento
          $paymentCondition = PaymentCondition::find($paymentConditionID);

          // Selecionamos o primeiro item de contrato deste cliente para
          // uso em função dos itens de contrato selecionados
          $installations = [];
          foreach ($billings as $billing) {
            if ($billing['installationid'] > 0) {
              $installations[] = $billing['installationid'];
            }
          }
          $filter = '';
          $defaultContractID = 0;
          if (count($installations) > 0) {
            $filter = 'AND installationID IN ('
              . implode(', ', $installations) . ')'
            ;
          }
          $sql = "SELECT contractID
                    FROM erp.installations
                   WHERE customerID = {$customerID} {$filter}
                   ORDER BY enddate NULLS FIRST
                   FETCH FIRST ROW ONLY;";
          $contracts = $this->DB->select($sql);
          if (count($contracts) > 0) {
            // Informa o número do primeiro contrato
            $defaultContractID = $contracts[0]->contractid;
          } else {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            throw new ModelNotFoundException(
              "Não foi possível obter as informações do contrato "
              . "do cliente"
            );
          }

          if ($chargeFromAssociate) {
            // Recuperamos a informação da matriz do associado
            $sql = "SELECT DISTINCT ON (S.entityid)
                           S.subsidiaryID AS ID
                      FROM erp.subsidiaries AS S
                     WHERE S.entityID = {$associateID}
                     ORDER BY S.entityID, S.headOffice DESC, S.name;"
            ;
            $associateSubsidiaries = $this->DB->select($sql);
            $associateUnityID = $associateSubsidiaries[0]->id;
          }

          if ($paymentMethodID == 5) {
            // Recuperamos a informação de configuração do meio de
            // pagamento
            $sql = "SELECT P.fineValue,
                           P.arrearInterestType,
                           P.arrearInterest
                      FROM erp.contracts AS C
                     INNER JOIN erp.plans AS P ON (C.planID = P.planID)
                     INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
                     WHERE C.contractID = $defaultContractID;";
            $billetInstructions = $this->DB->select($sql);
            $billetOpt = $billetInstructions[0];
            unset($billetInstructions);

            $sql = "SELECT ((parameters::jsonb - 'instructionID') - 'instructionDays')::json AS parameters,
                           parameters::jsonb->'instructionID' AS instructionID,
                           parameters::jsonb->'instructionDays' AS instructionDays
                      FROM erp.definedMethods
                     WHERE definedMethodID = {$definedMethodID}";
            $billetInstructions = $this->DB->select($sql);
            $billetInstruction = $billetInstructions[0];
            unset($billetInstructions);
          }

          // Inicalizamos as variáveis de controle
          $primaryInvoiceID = 0;

          // Descobrimos os intervalos entre parcelas
          $intervals = explode('/', $paymentCondition->paymentinterval);

          // Sempre emitimos ao menos uma cobrança
          $parcel = 1;
          $totalOfParcels = ($numberOfInstallments > 1)
            ? $numberOfInstallments
            : 0
          ;

          // Atribuímos a primeira data de vencimento como sendo a data
          // informada no formulário
          $firstDueDate = $dueDate->copy();

          // Processamos cada parcela a ser cobrada
          do {
            // Formata a data de vencimento
            $dueDateFmt = $dueDate->format('Y-m-d');

            // Determina o número da parcela
            $numberOfParcel = ($numberOfInstallments > 1)
              ? $parcel
              : 0
            ;

            // Precisamos criar uma nova fatura
            $invoice = new Invoice();
            $invoice->contractorid = $contractor->id;
            if ($chargeFromAssociate) {
              $invoice->customerid = $associateID;
              $invoice->subsidiaryid = $associateUnityID;
            } else {
              $invoice->customerid = $customerID;
              $invoice->subsidiaryid = $subsidiaryID;
            }
            $invoice->invoicedate = Carbon::now();
            // Indicamos que este mês é o mês de referência desta
            // cobrança
            $invoice->referencemonthyear = Carbon::now()->format('m/Y');
            $invoice->duedate = $dueDate;
            $invoice->paymentmethodid = $paymentMethodID;
            if ($definedMethodID > 0) {
              $invoice->definedmethodid = $definedMethodID;
            }
            if ($numberOfInstallments > 1) {
              // O valor da fatura é o valor da mensalidade
              $invoice->invoicevalue = $parcel;

              if ($parcel > 1) {
                // Precisamos informar qual a fatura principal para que
                // possamos imprimir as mesmas informações
                $invoice->primaryinvoiceid = $primaryInvoiceID;
              }
            } else {
              // O valor da fatura é o valor total a ser cobrado
              $invoice->invoicevalue = $valueToPay;
            }
            $invoice->save();
            $invoiceID = $invoice->invoiceid;

            // Precisamos incluir os itens cobrados na fatura
            if ($parcel === 1)  {
              // É a primeira parcela, então apuramos todos os valores,
              // que são adicionados. Nas demais parcelas (se houver),
              // os valores serão copiados

              // Armazena o ID da fatura principal
              $primaryInvoiceID = $invoiceID;

              // Determina o início do período
              $periodStart = Carbon::now()->startOfMonth()->format('Y-m-d');

              foreach ($billings as $billing) {
                // Convertemos o valor cobrado
                $value = $this->toFloat($billing['value']);

                if ( (in_array($chosenPayment, ['unusual', 'closing'])
                      && array_key_exists('marked', $billing))
                     || ($chosenPayment = 'another') ) {
                  // Acrescentamos os valores cobrados
                  if ($billing['ascertained'] == 1) {
                    // Este é um período apurado virtualmente, então
                    // executa a apuração definitiva, gerando os
                    // registros necessários
                    $sql = "SELECT erp.performedServiceInPeriod(
                                     '{$periodStart}',
                                     {$billing['installationid']},
                                     {$userID},
                                     CURRENT_DATE
                                   ) AS id;";
                    $ascertainedPeriod = $this->DB->select($sql);

                    $ascertainedPeriodID = 0;
                    if ($ascertainedPeriod[0]->id > 0) {
                      // Tivemos um período acertado, então armazena o ID
                      $ascertainedPeriodID = $ascertainedPeriod[0]->id;
                    }

                    // Colocamos o período apurado dentro da fatura aberta
                    $sql = "UPDATE erp.billings
                               SET invoiceID = {$invoiceID},
                                   invoiced = true
                             WHERE installationID = {$billing['installationid']}
                               AND ascertainedPeriodID = {$ascertainedPeriodID};";
                    $this->DB->select($sql);

                    // Colocamos o período cobrado, se necessário, nesta
                    // fatura
                    $sql = "UPDATE erp.billedPeriods
                               SET invoiceID = {$invoiceID}
                             WHERE installationID = {$billing['installationid']}
                               AND invoiceID IS NULL;";
                    $this->DB->select($sql);
                  } else {
                    if ($billing['billindid'] > 0) {
                      // Incluímos correções realizadas em tempo de
                      // edição nos valores cobrados já registrados e
                      // adicionamo-os à fatura atual
                      $sql = "UPDATE erp.billings
                                 SET name = '{$billing['name']}',
                                     value = {$value},
                                     invoiceID = {$invoiceID},
                                     invoiced = true
                               WHERE billingid = {$billing['billindid']};";
                      $this->DB->select($sql);
                    } else {
                      // Adicionamos novos valores diretamente à fatura
                      // atual
                      $contractID = ($billing['contractid'] > 0)
                        ? $billing['contractid']
                        : $defaultContractID
                      ;
                      $installationID = ($billing['installationid'] > 0)
                        ? $billing['installationid']
                        : 'NULL'
                      ;
                      $sql = "INSERT INTO erp.billings (contractorID,
                                     contractID, installationID, billingDate,
                                     name, value, invoiceID, invoiced,
                                     addMonthlyAutomatic, isMonthlyPayment,
                                     createdByUserID, updatedByUserID)
                              VALUES ({$contractor->id}, {$contractID},
                                      {$installationID}, CURRENT_DATE,
                                      '{$billing['name']}', {$value},
                                      {$invoiceID}, TRUE, FALSE, FALSE,
                                      {$userID}, {$userID});";
                      $this->DB->select($sql);
                    }
                  }
                }
              }
            }

            // Registra a cobrança conforme o meio de pagamento
            // escolhido
            switch ($paymentMethodID) {
              case 3:
                // Cartão de débito
              case 4:
                // Cartão de crédito
              case 6:
                // Transferência bancária
                $sql = "INSERT INTO erp.payments (contractorID, invoiceID,
                               dueDate, valueToPay, paymentMethodID,
                               paymentSituationID, parcel, numberofparcels)
                        VALUES ({$contractor->id}, {$invoiceID},
                                '{$dueDateFmt}', {$valueToPay},
                                {$paymentMethodID},
                                " . PaymentSituation::RECEIVABLE . ",
                                {$numberOfParcel}, {$totalOfParcels});";
                $this->DB->select($sql);

                break;
              case 5:
                // Boleto bancário

                // Atualizamos o contador de emissões deste meio de
                // pagamento
                $sql = "UPDATE erp.definedMethods
                           SET billingCounter = billingCounter + 1
                         WHERE definedMethodID = {$definedMethodID}
                     RETURNING billingCounter;";
                $definedMethod = $this->DB->select($sql);
                $billingCounter = 0;
                if ($definedMethod[0]->billingcounter > 0) {
                  // Os dados do período cobrado
                  $billingCounter = $definedMethod[0]->billingcounter;
                }

                // Determinamos o número de identificação do boleto no
                // banco
                $sql = "SELECT A.bankID,
                               A.agencyNumber,
                               A.accountNumber,
                               A.wallet,
                               erp.buildBankIdentificationNumber(
                                 A.bankID,
                                 A.agencyNumber,
                                 A.accountNumber,
                                 A.wallet,
                                 {$billingCounter},
                                 {$invoiceID},
                                 ((D.parameters::jsonb - 'instructionID') - 'instructionDays')::json
                               ) AS ourNumber
                          FROM erp.invoices AS I
                         INNER JOIN erp.definedMethods AS D USING (definedMethodID)
                         INNER JOIN erp.accounts AS A USING (accountID)
                         WHERE I.invoiceID = {$invoiceID};";
                $bank = $this->DB->select($sql);

                // Inserimos o boleto para cobrança
                $sql = "INSERT INTO erp.bankingBilletPayments (
                               contractorID, invoiceID, dueDate,
                               valueToPay, paymentMethodID,
                               paymentSituationID, definedMethodID,
                               bankCode, agencyNumber, accountNumber,
                               wallet, billingCounter, parameters,
                               ourNumber, fineValue, arrearInterestType,
                               arrearInterest, instructionID,
                               instructionDays, droppedTypeID, parcel,
                               numberofparcels)
                        VALUES ({$contractor->id}, {$invoiceID},
                                '{$dueDateFmt}', {$installmentValue},
                                {$paymentMethodID},
                                " . PaymentSituation::RECEIVABLE . ",
                                {$definedMethodID}, '{$bank[0]->bankid}',
                                '{$bank[0]->agencynumber}', 
                                '{$bank[0]->accountnumber}',
                                '{$bank[0]->wallet}', {$billingCounter},
                                '{$billetInstruction->parameters}',
                                '{$bank[0]->ournumber}',
                                {$billetOpt->finevalue},
                                {$billetOpt->arrearinteresttype},
                                {$billetOpt->arrearinterest},
                                {$billetInstruction->instructionid},
                                {$billetInstruction->instructiondays},
                                " . BilletStatus::NOT_REGISTERED . ",
                                {$numberOfParcel}, {$totalOfParcels}
                              );";
                $this->DB->select($sql);

                break;
              default:
                // Dinheiro, cheque e outros meios de pagamento
                $sql = "INSERT INTO erp.payments (contractorID, invoiceID,
                               dueDate, valueToPay, paymentMethodID,
                               paymentSituationID, parcel, numberofparcels)
                        VALUES ({$contractor->id}, {$invoiceID},
                                '{$dueDateFmt}', {$valueToPay},
                                {$paymentMethodID},
                                " . PaymentSituation::RECEIVABLE . ",
                                {$numberOfParcel}, {$totalOfParcels});";
                $this->DB->select($sql);
            }
            
            if ($numberOfInstallments > 1) {
              // Passamos para a próxima parcela
              $dueDate = $firstDueDate->copy();
              if ($paymentCondition->timeunit == 'DAY') {
                $dueDate->addDays($intervals[$parcel]);
              } else {
                $dueDate->addMonths($intervals[$parcel] - 1);
              }
            }
            $parcel++;
          } while ($parcel <= $numberOfInstallments);

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Emitida cobrança para o cliente {customername} "
            . "do contratante '{contractor}' com sucesso.",
            [
              'customername' => $customerName,
              'contractor' => $contractor->name
            ]
          );

          // Alerta o usuário
          $this->flash("success", "A cobrança foi emitida com sucesso.");

          break;
        default:
          // code...
          break;
      }
          
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\Payments' ]
      );
      
      // Redireciona para a página de gerenciamento de cobranças
      return $this->redirect($response,
        'ERP\Financial\Payments')
      ;
    } else {
      // Carrega um conjunto de valores vazios
      $emptyPayment = [
        'customerid' => 0,
        'customername' => '',
        'subsidiaryid' => 0,
        'subsidiaryname' => '',
        'associateid' => 0,
        'startdate' => Carbon::now()->startOfMonth()->addMonth()->format('d/m/Y'),
        'numberofparcels' => 0,
        'duedate' => Carbon::now()->format('d/m/Y'),
        'value' => '0,00',
        'valuetopay' => '0,00',
        'paymentconditionid' => 0,
        'paymentmethodid' => 0,
        'definedmethodid' => 0,
        'numberofinstallments' => 0,
        'installmentvalue' => '0,00'
      ];
      $this->validator->setValues($emptyPayment);
    }

    // Exibe um formulário para adição de umo cobrança

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push(
      'Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push(
      'Cobranças',
      $this->path('ERP\Financial\Payments')
    );
    $this->breadcrumb->push(
      'Adicionar cobrança',
      $this->path('ERP\Financial\Payments\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de {chosenPayment} no contratante "
      . "'{contractor}'.",
      [
        'chosenPayment' => $typeOfPayments[$chosenPayment]['name'],
        'contractor' => $contractor->name
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/payments/newpayment.twig',
      [
        'formMethod' => 'POST',
        'chosenPayment' => $chosenPayment,
        'typeOfPayment' => $typeOfPayments[$chosenPayment],
        'paymentConditions' => $paymentConditions
      ]
    );
  }

  /**
   * Exibe um formulário para edição de umo cobrança, quando
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
    } catch (RuntimeException $exception) {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Financial\Payments']
      );

      // Redireciona para a página de gerenciamento de lançamentos
      return $this->redirect($response, 'ERP\Financial\Payments');
    }

    try {
      // Recupera as informações do lançamento
      $paymentID = $args['paymentID'];
      $payment = Payment::join('contracts', 'payments.contractid', '=',
            'contracts.contractid'
          )
        ->join('entities as customers', 'contracts.customerid', '=',
            'customers.entityid'
          )
        ->join('subsidiaries', 'contracts.subsidiaryid', '=',
            'subsidiaries.subsidiaryid'
          )
        ->join('installations', 'payments.installationid', '=',
            'installations.installationid'
          )
        ->join('users AS createduser', 'payments.createdbyuserid', '=',
            'createduser.userid'
          )
        ->join('users AS updateduser', 'payments.updatedbyuserid', '=',
            'updateduser.userid'
          )
        ->where('payments.contractorid', '=', $contractor->id)
        ->where('payments.paymentid', '=', $paymentID)
        ->get([
            'payments.*',
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

      if ($payment->isEmpty()) {
        throw new ModelNotFoundException("Não temos nenhum lançamento "
          . "com o código {$paymentID} cadastrado"
        );
      }
      $payment = $payment
        ->first()
        ->toArray()
      ;
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento código "
        . "{contractID}.",
        [ 'contractID' => $paymentID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "lançamento."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Financial\Payments']
      );

      // Redireciona para a página de gerenciamento de lançamentos
      return $this->redirect($response, 'ERP\Financial\Payments');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do lançamento '{name}' do "
        . "cliente '{customername}' no contratante {contractor}.",
        [
          'name' => $payment['name'],
          'customername' => $payment['customername'],
          'contractor' => $contractor->name
        ]
      );

      // Valida os dados
      //$this->validator->validate($request,
      //  $this->getValidationRules(false)
      //);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados

        // Recupera os dados modificados do lançamento
        $paymentData = $this->validator->getValues();

        try {
          // Não permite modificar o contratante
          unset($paymentData['contractorid']);

          // Iniciamos a transação
          $this->DB->beginTransaction();

          $paymentChanged = Payment::findOrFail($paymentID);
          $paymentChanged->fill($paymentData);
          // Adiciona o responsável pela modificação
          $paymentChanged->updatedbyuserid =
            $this->authorization->getUser()->userid
          ;
          $paymentChanged->save();

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Modificado o lançamento '{name}' no item de "
            . "contrato '{installation}' do cliente '{customerName}' "
            . "no contratante '{contractor}' com sucesso.",
            [
              'name' => $paymentData['name'],
              'installation' => $paymentData['installationnumber'],
              'customerName' => $paymentData['customername'],
              'contractor' => $contractor->name
            ]
          );

          // Alerta o usuário
          $this->flash("success", "O lançamento '{name}' foi "
            . "modificado com sucesso.",
            [ 'name'  => $paymentData['name'] ]
          );

          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Financial\Payments' ]
          );

          // Redireciona para a página de gerenciamento de lançamentos
          return $this->redirect($response, 'ERP\Financial\Payments');
        } catch (QueryException $exception) {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' no item de contrato '{installation}' "
            . "do cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [
              'name' => $paymentData['name'],
              'installation' => $paymentData['installationnumber'],
              'customerName'  => $paymentData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno no banco de "
            . "dados."
          );
        } catch (Exception $exception) {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' no item de contrato '{installation}' "
            . "do cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [
              'name' => $paymentData['name'],
              'installation' => $paymentData['installationnumber'],
              'customerName'  => $paymentData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($payment);
    }

    // Exibe um formulário para edição de um lançamento

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push(
      'Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push(
      'Lançamentos',
      $this->path('ERP\Financial\Payments')
    );
    $this->breadcrumb->push(
      'Editar',
      $this->path('ERP\Financial\Payments\Edit', [
        'paymentID' => $paymentID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do lançamento '{name}' do cliente "
      . "'{customername}' no contratante {contractor}.",
      [
        'name' => $payment['name'],
        'customername' => $payment['customername'],
        'contractor' => $contractor->name
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/payments/payment.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }

  /**
   * Remove a cobrança.
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
    $this->debug("Processando à remoção de cobrança.");

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $paymentID = $args['paymentID'];

    try {
      // Recupera as informações do lançamento
      $paymentData = Payment::join('contracts', 'payments.contractid',
            '=', 'contracts.contractid'
          )
        ->join('entities as customer', 'contracts.customerid', '=',
            'customer.entityid'
          )
        ->where('payments.paymentid', '=', $paymentID)
        ->get([
            'payments.paymentid AS id',
            'customer.name AS customername',
          ])
        ->first()
      ;

      $payment = Payment::where('contractorid', '=', $contractor->id)
        ->where('paymentid', '=', $paymentID)
        ->firstOrFail()
      ;

      // Agora apaga o lançamento do valor a ser cobrado
      $payment->delete();

      // Registra o sucesso
      $this->info("O lançamento '{name}' do cliente '{customername}' "
        . "do contratante '{contractor}' foi removido com sucesso.",
        [
          'name' => $paymentData->name,
          'customername' => $paymentData->customername,
          'contractor' => $contractor->name
        ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o lançamento "
              . "{$paymentData->name}",
            'data' => "Delete"
          ])
      ;
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {paymentID} para remoção.",
        [ 'paymentID' => $paymentID ]
      );

      $message = "Não foi possível localizar o lançamento para "
        . "remoção."
      ;
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [
          'name'  => $paymentData->name,
          'customername'  => $paymentData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o lançamento. Erro interno "
        . "no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [
          'name'  => $paymentData->name,
          'customername'  => $paymentData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage()
        ]
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
   * Realiza a baixa da cobrança.
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
  public function drop(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando a baixa de cobrança.");

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $paymentID = $args['paymentID'];

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera a ação a ser realizada
    $action = $postParams['action'];

    switch ($action) {
      case 'pay':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'paiddate' => V::date('d/m/Y')
            ->setName('Data do pagamento'),
          'creditdate' => V::date('d/m/Y')
            ->setName('Data do crédito'),
          'paidvalue' => V::floatVal()
            ->notBlank()
            ->setName('Valor pago'),
          'paidreasons' => V::optional(
                V::notBlank()
              )
            ->setName('Observação')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $paidDate = $this->toSQLDate($paymentData['paiddate']);
          $creditDate = $this->toSQLDate($paymentData['creditdate']);
          $paidvalue = $paymentData['paidvalue'];
          $paidreasons = $paymentData['paidreasons'];

          try {
            // Confirmamos o pagamento
            $sql = "SELECT erp.pay({$contractor->id}, {$paymentID}, "
              . "{$paymentMethodID}, '{$paidDate}', '{$creditDate}', "
              . "$paidvalue, '{$paidreasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Pagamento liquidado com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível confirmar o pagamento.'
                    . ' Erro: ' . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível confirmar o pagamento. '
                  . 'Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'abatement':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'originalvalue' => V::floatVal()
            ->notBlank()
            ->setName('Valor do documento'),
          'discountvalue' => V::floatVal()
            ->notBlank()
            ->setName('Abatimento'),
          'discountreasons' => V::optional(
                V::notBlank()
              )
            ->setName('Observação')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();

          // Analisa se o desconto oferecido não é maior do que o valor
          // do documento
          if (floatval($paymentData['discountvalue']) > floatval($paymentData['originalvalue'])) {
            // Informamos este erro
            $errors = [
              'discountvalue' => 'O abatimento não pode ser maior do '
                . 'que o valor do documento'
            ];

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível conceder o abatimento',
                  'data' => $errors
                ])
            ;
          }

          $paymentMethodID = $paymentData['paymentMethodID'];
          $abatementValue = $paymentData['discountvalue'];
          $reasons = $paymentData['discountreasons'];

          try {
            // Concedemos o abatimento
            $sql = "SELECT erp.abatement({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, "
              . "$abatementValue, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Abatimento concedido com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível conceder o abatimento.'
                    . ' Erro: ' . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível conceder o abatimento. '
                  . 'Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'ungrantAbatement':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Cancelamos o abatimento concedido
            $sql = "SELECT erp.ungrantAbatement({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Abatimento cancelado com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível cancelar o abatimento.'
                    . ' Erro: ' . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível cancelar o abatimento. '
                  . 'Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'changeDuedate':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'currentduedate' => V::date('d/m/Y')
            ->setName('Data de vencimento atual'),
          'newduedate' => V::date('d/m/Y')
            ->setName('Nova data de vencimento'),
          'duedatereasons' => V::optional(
                V::notBlank()
              )
            ->setName('Observação')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();

          // Analisa se a nova data de vencimento é diferente da data
          // de vencimento atual
          if ($paymentData['currentduedate'] === $paymentData['newduedate']) {
            // Informamos este erro
            $errors = [
              'newduedate' => 'Informe uma data de vencimento diferente'
            ];

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível alterar a data de '
                    . 'vencimento',
                  'data' => $errors
                ])
            ;
          }

          $paymentMethodID = $paymentData['paymentMethodID'];
          $newDuedate = $this->toSQLDate($paymentData['newduedate']);
          $reasons = $paymentData['duedatereasons'];

          try {
            // Confirmamos o pagamento
            $sql = "SELECT erp.changeDuedate({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$newDuedate}', "
              . "'{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Data do vencimento do pagamento '
                    . 'alterada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível alterar a data de '
                    . 'vencimento. Erro: ' . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível  alterar a data de '
                  . 'vencimento. Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'cancel':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Cancelamos o abatimento concedido
            $sql = "SELECT erp.cancelPayment({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Título cancelado com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível cancelar o título.'
                    . ' Erro: ' . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível cancelar o título. '
                  . 'Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'creditBlocked':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos a negativação do título
            $sql = "SELECT erp.creditBlocked({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de negativar título '
                    . 'efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar a '
                    . 'negativação do título. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar a negativação '
                  . 'do título. Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'creditUnblocked':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos a sustação da negativação do título
            $sql = "SELECT erp.creditUnblocked({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de sustar negativação do '
                    . 'título efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar a sustação '
                    . 'da negativação do título. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar a sustação da '
                  . 'negativação do título. Informe os dados '
                  . 'necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'protest':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos o protesto do título
            $sql = "SELECT erp.protest({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de protestar título '
                    . 'efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar o '
                    . 'protesto do título. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar o protesto do '
                  . 'título. Informe os dados necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'unprotest':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos a sustação do protesto do título
            $sql = "SELECT erp.unprotest({$contractor->id}, "
              . "{$paymentID}, {$paymentMethodID}, '{$reasons}') AS result;"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de sustar protesto do '
                    . 'título efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar a sustação '
                    . 'do protesto do título. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar a sustação do '
                  . 'protesto do título. Informe os dados '
                  . 'necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'sentToDunningAgency':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos o envio do título para agência de cobrança
            $sql = "UPDATE erp.payments
                       SET restrictionID = (restrictionID | 4)
                    WHERE paymentID = {$paymentID}
                      AND contractorid = {$contractor->id};"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de envio do título para '
                    . 'agência de cobrança efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar o envio do '
                    . 'título para agência de cobrança. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar o envio do '
                  . 'título para agência de cobrança. Informe os dados '
                  . 'necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      case 'retireFromDunningAgency':
        // Valida os dados
        $this->validator->validate($request, [
          'paymentMethodID' => V::notBlank()
            ->intVal()
            ->setName('ID do pagamento'),
          'reasonsdescription' => V::notBlank()
            ->setName('Motivo')
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados do pagamento
          $paymentData = $this->validator->getValues();
          $paymentMethodID = $paymentData['paymentMethodID'];
          $reasons = $paymentData['reasonsdescription'];

          try {
            // Solicitamos retirada do título da agência de cobrança
            $sql = "UPDATE erp.payments
                       SET restrictionID = (restrictionID & ~4)
                    WHERE paymentID = {$paymentID}
                      AND contractorid = {$contractor->id};"
            ;
            $this->DB->select($sql);

            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'OK',
                  'params' => $request->getParams(),
                  'message' => 'Solicitação de retirada do título da '
                    . 'âgência de cobrança efetuada com sucesso',
                  'data' => null
                ])
            ;
          } catch (QueryException $e) {
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withJson([
                  'result' => 'NOK',
                  'params' => $request->getParams(),
                  'message' => 'Não foi possível solicitar a retirada '
                    . 'do título da agência de cobrança. Erro: '
                    . $e->getMessage(),
                  'data' => NULL
                ])
            ;
          }
        } else {
          // Recupera as mensagens de erro
          $validationErrors = $this->validator->getErrors();

          $errors = [];
          foreach ($validationErrors as $field => $error) {
            $errors[$field] = implode(', ', $error);
          }

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getParams(),
                'message' => 'Não foi possível solicitar a retirada do '
                  . 'título da agência de cobrança. Informe os dados '
                  . 'necessários.',
                'data' => $errors
              ])
          ;
        }

        break;
      default:
        // Ação inválida
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getParams(),
              'message' => "A ação {$action} não foi implementada",
              'data' => null
            ])
        ;
        break;
    }
  }

  /**
   * Obtém um ou mais boletos em função do documento informado.
   *
   * @param int $documentID
   *   O ID do documento
   * @param bool $single
   *   O indicativo que o ID corresponde a um único boleto
   *
   * @return array
   *   A matriz com os boletos
   */
  protected function getBillets(
    int $documentID,
    bool $single
  ): array
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // Recuperamos os dados do emissor
    $emitter = Contractor::join("entitiestypes",
          "entities.entitytypeid", '=', "entitiestypes.entitytypeid"
        )
      ->join('subsidiaries', 'entities.entityid', '=',
          'subsidiaries.entityid'
        )
      ->join( 'documenttypes', 'subsidiaries.regionaldocumenttype', '=',
          'documenttypes.documenttypeid'
        )
      ->join('cities', 'subsidiaries.cityid', '=', 'cities.cityid')
      ->where("entities.contractor", "true")
      ->where('entities.entityid', '=', $contractorID)
      ->get([
          'entities.name',
          'entitiestypes.juridicalperson as juridicalperson',
          'subsidiaries.name AS subsidiaryname',
          'documenttypes.name AS regionaldocumenttypename',
          'subsidiaries.regionaldocumentnumber',
          'subsidiaries.regionaldocumentstate',
          'subsidiaries.nationalregister',
          'subsidiaries.address',
          'subsidiaries.streetnumber',
          'subsidiaries.complement',
          'subsidiaries.district',
          'cities.name AS cityname',
          'cities.state',
          'subsidiaries.postalcode'
        ])
      ->first()
    ;

    // Iniciamos nossa consulta para obter as informações do(s)
    // pagamento(s)
    $paymentQry = BankingBilletPayment::join('invoices',
          'bankingbilletpayments.invoiceid', '=', 'invoices.invoiceid'
        )
      ->join('entities as customers', 'invoices.customerid', '=',
          'customers.entityid'
        )
      ->join("entitiestypes", "customers.entitytypeid", '=',
          "entitiestypes.entitytypeid"
        )
      ->join('subsidiaries', 'invoices.subsidiaryid', '=',
          'subsidiaries.subsidiaryid'
        )
      ->join('documenttypes', 'subsidiaries.regionaldocumenttype', '=',
          'documenttypes.documenttypeid'
        )
      ->join('cities', 'subsidiaries.cityid', '=', 'cities.cityid')
      ->join('paymentmethods', 'bankingbilletpayments.paymentmethodid',
          '=', 'paymentmethods.paymentmethodid'
        )
      ->where('bankingbilletpayments.contractorid', '=', $contractorID)
    ;

    if ($single) {
      // Iremos recuperar apenas um pagamento
      $paymentQry
        ->where('bankingbilletpayments.paymentid', '=', $documentID)
      ;
    } else {
      // Iremos recuperar todos os pagamentos que compõe o carnê
      $paymentQry
        ->where('invoices.carnetid', '=', $documentID)
      ;
    }

    // Concluimos a consulta
    $payments = $paymentQry
      ->orderBy('bankingbilletpayments.duedate')
      ->get([
          'bankingbilletpayments.*',
          'invoices.customerid',
          'customers.name AS customername',
          'customers.entitytypeid',
          'entitiestypes.name AS entitytypename',
          'entitiestypes.cooperative',
          'entitiestypes.juridicalperson',
          'invoices.subsidiaryid',
          'subsidiaries.name AS subsidiaryname',
          'subsidiaries.nationalregister',
          'subsidiaries.regionaldocumenttype',
          'documenttypes.name AS regionaldocumenttypename',
          'subsidiaries.address',
          'subsidiaries.streetnumber',
          'subsidiaries.complement',
          'subsidiaries.district',
          'subsidiaries.postalcode',
          'cities.name AS cityname',
          'cities.state',
          $this->DB->raw('getMails(customers.contractorid, customers.entityid, subsidiaries.subsidiaryid, 3) AS emails'),
          'invoices.referencemonthyear',
          'invoices.invoicedate',
          $this->DB->raw(''
            . 'CASE'
            . '  WHEN invoices.primaryinvoiceid IS NULL THEN invoices.invoiceid'
            . '  ELSE invoices.primaryinvoiceid '
            . 'END AS primaryinvoiceid'
          )
        ])
    ;

    if ($payments->isEmpty()) {
      throw new ModelNotFoundException("Não temos nenhuma cobrança "
        . "com o código {$documentID} cadastrada"
      );
    }

    // Obtém as configurações do boleto
    $parameters = json_decode($payments[0]->parameters, true);

    // Criamos o agente emissor (Beneficiário). No nosso caso, o
    // garantidor é a mesma entidade que o emissor
    $emitterAgent = (new AgentEntity())
      ->setName($emitter->name)
      ->setDocumentNumber($emitter->nationalregister)
      ->setAddress($emitter->address)
      ->setStreetNumber($emitter->streetnumber)
      ->setComplement($emitter->complement)
      ->setDistrict($emitter->district)
      ->setPostalCode($emitter->postalcode)
      ->setCity($emitter->cityname)
      ->setState($emitter->state)
      // A imagem para impressão
      ->setLogoAsBase64($this->getContractorLogo($contractor->uuid))
      // Os dados da conta bancária
      ->setAgencyNumber($payments[0]->agencynumber)
      ->setAccountNumber($payments[0]->accountnumber)
    ;

    // Percorremos os pagamentos para gerar os boletos
    $billings = [];
    foreach ($payments AS $payment) {
      // Criamos o agente pagador
      $payerAgent = (new AgentEntity())
        ->setName($payment->customername)
        ->setDocumentNumber($payment->nationalregister)
        ->setEmails(json_decode($payment->emails))
        ->setAddress($payment->address)
        ->setStreetNumber($payment->streetnumber)
        ->setComplement($payment->complement)
        ->setDistrict($payment->district)
        ->setPostalCode($payment->postalcode)
        ->setCity($payment->cityname)
        ->setState($payment->state)
      ;

      // Criamos o boleto
      $billet = (BankingBilletFactory::loadBankFromCode($payment['bankcode']))
        // Informações do beneficiário e pagador
        ->setEmitter($emitterAgent)
        ->setPayer($payerAgent)
        ->setGuarantor($emitterAgent)
        // Informações do contrato com o banco emissor
        ->setWallet($payment->wallet)
        ->setSequentialNumber($payment->billingcounter)
        ->setAdditionalParameters($parameters)
        // Informações do documento
        ->setDateOfDocument(
            Carbon::createFromFormat('Y-m-d', $payment->invoicedate)
              ->locale('pt_BR')
          )
        ->setDocumentNumber($payment->invoiceid)
        ->setDocumentValue($this->toFloat($payment->valuetopay))
        ->setDateOfExpiration(
            Carbon::createFromFormat('Y-m-d', substr($payment->duedate, 0, 10))
              ->locale('pt_BR')
          )
        // O valor dos juros de mora
        ->setArrearInterestType(intval($payment->arrearinteresttype))
        ->setArrearInterestPerDay(floatval($payment->arrearinterest))
        // O valor da multa
        ->setFineValue(floatval($payment->finevalue))
        // A instrução a ser executada no título após o vencimento, caso
        // não seja pago
        ->setInstructionAfterExpiration($payment->instructionid, $payment->instructiondays)
        ->setAutoInstructionsText()
        // As referências do título sendo cobrado
        ->setReferenceMonth($payment->referencemonthyear)
        ->setParcel($payment->parcel, $payment->numberofparcels)
      ;

      if ($single) {
        // Recuperamos as informações dos valores cobrados na fatura
        // para a qual o boleto foi emitido
        $invoiceID = $payment->primaryinvoiceid;

        // Criamos um ordenamento em que as cobranças estejam agrupadas
        // de forma que os serviços prestados para o próprio cliente
        // estejam em primeiro, seguido dos serviços para os quais ele
        // figura como o pagante. Também, dentro de um mesmo item de
        // contrato, colocamos as mensalidades primeiramente, seguido de
        // outros valores ordenados por data
        $order = ''
          . 'CASE WHEN contracts.customerID = contracts.customerPayerID THEN 0 ELSE 1 END, '
          . 'billings.contractID, '
          . 'billings.installationID NULLS LAST, '
          . 'billings.ascertainedPeriodID NULLS LAST, '
          . 'billings.billingDate'
        ;
        $sql = "SELECT B.billingID AS id,
                       B.customerID,
                       B.customerName,
                       B.cooperative,
                       B.juridicalperson,
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
                       B.ascertainedPeriodID,
                       B.fullcount
                  FROM erp.getBillingsData({$contractor->id}, 0, 0,
                    {$invoiceID}, NULL, NULL, FALSE, '$order', NULL, NULL) AS B
                 WHERE B.granted = FALSE AND B.renegotiated = FALSE
                 ORDER BY B.contractid, B.plate NULLS LAST, B.ascertainedPeriodID NULLS LAST, B.billingdate;"
        ;
        $billings = $this->DB->select($sql);

        // Monstamos o conteúdo do detalhamento da fatura
        $details = [];
        $cols = [
          'left' => [],
          'right' => [],
          'amount' => 0
        ];
        $lastContractNumber = '';

        if (count($billings) > 0) {
          // Para cada item cobrado, montamos as informações do
          // detalhamento, quebrando as informações em duas colunas
          $lastCustomerID = $payment->customerid;
          //$lastSubsidiaryID = 0;
          $lastContractID = 0;
          $side = 'none';
          $lastPlate = '';
          $lastInstallationID = 0;
          $totalOfInstallations = 0;
          foreach ($billings as $billing) {
            if ($billing->customerid !== $lastCustomerID) {
              // Mudamos de cliente. Isto ocorre porque podemos cobrar numa
              // mesma fatura valores de clientes diferentes. Na fatura do
              // cliente pagador aparecem os detalhes dos serviços prestados
              // à veículos pertencentes à sua conta, seguidos dos demais
              // veículos para os quais ele figura como pagante
              if (count($cols['left']) > 0) {
                // Adicionamos as colunas
                $details[] = [
                  'type' => 'cols',
                  'cols' => $cols,
                  'contract' => $lastContractNumber
                ];

                // Reiniciamos
                $cols = [
                  'left' => [],
                  'right' => [],
                  'amount' => 0
                ];

                $side = 'none';
              }

              // Inserimos a informação do novo grupo de valores
              $details[] = [
                'type' => 'group',
                'name' => $billing->customername
              ];

              $lastCustomerID = $billing->customerid;
              $lastContractID = $billing->contractid;
              $lastContractNumber = $billing->contractnumber;
            } else {
              if ($billing->contractid !== $lastContractID) {
                // Mudamos de contrato do cliente. Isto ocorre porque podemos
                // cobrar numa mesma fatura valores de contratos diferentes
                // do mesmo cliente. Na fatura do cliente pagador aparecem
                // os detalhes dos serviços prestados à veículos pertencentes
                // à um contrato, seguido de um espaço e dos demais serviços
                // do contrato seguinte
                if (count($cols['left']) > 0) {
                  // Adicionamos as colunas
                  $details[] = [
                    'type' => 'cols',
                    'cols' => $cols,
                    'contract' => $lastContractNumber
                  ];

                  // Reiniciamos
                  $cols = [
                    'left' => [],
                    'right' => [],
                    'amount' => 0
                  ];

                  // Inserimos um espaço
                  $details[] = [
                    'type' => 'space',
                    'contract' => $billing->contractNumber
                  ];

                  $side = 'none';
                }

                $lastContractID = $billing->contractid;
                $lastContractNumber = $billing->contractnumber;
              }
            }

            //if ($billing->cooperative) {
            //  // Para cooperativas, precisamos informar os associados
            //  if ($billing->affiliated) {
            //    if ($billing->subsidiaryid !== $lastSubsidiaryID) {
            //      // Inserimos a informação do novo subgrupo de valores
            //      $details[] = [
            //        'type' => 'subgroup',
            //        'name' => 'Associado: ' . $billing->subsidiaryname
            //      ];
            //      
            //      $lastSubsidiaryID = $billing->subsidiaryid;
            //    }
            //  }
            //}

            // Contamos a quantidade de itens de contrato
            if ($billing->installationid !== $lastInstallationID) {
              $cols['amount']++;
              $totalOfInstallations++;
              $lastInstallationID = $billing->installationid;
            }

            if ($billing->ascertainedperiodid) {
              // Precisamos acrescentar a informação do veículo para o qual
              // o serviço foi prestado
              $periodID = $billing->ascertainedperiodid;

              // Obtemos a informação do período apurado
              $ascertainedPeriod = AscertainedPeriod::where(
                    'ascertainedperiodid', '=', $periodID
                  )
                ->get([
                    $this->DB->raw("to_char(startdate, 'DD/MM') AS startedat"),
                    $this->DB->raw("to_char(enddate, 'DD/MM') AS endedat"),
                    'grossvalue',
                    'discountvalue'
                  ])
                ->first()
              ;

              // Para este período, obtemos as informações dos veículos
              // rastreados, desconsiderando os descontos
              $periodDetails = AscertainedPeriodDetail::join('vehicles',
                    'ascertainedperioddetails.vehicleid', '=',
                    'vehicles.vehicleid'
                  )
                ->where('ascertainedperioddetails.ascertainedperiodid',
                    '=', $periodID
                  )
                ->whereNull('subsidyid')
                ->orderBy('startedat')
                ->get([
                    $this->DB->raw("to_char(periodStartedAt, 'DD/MM') AS startedat"),
                    $this->DB->raw("to_char(periodEndedAt, 'DD/MM') AS endedat"),
                    'plate'
                  ])
              ;

              // Alternamos a coluna
              $side = ($side === 'left')
                ? 'right'
                : 'left'
              ;

              // Registramos o último veículo
              $lastPlate = $billing->plate;

              if (count($periodDetails) > 1) {
                // Temos mais de um veículo a ser exibido, então precisamos
                // exibir a informação para o cliente da mudança do veículo
                foreach ($periodDetails as $periodDetail) {
                  $cols[$side][] = [
                    'plate' => $periodDetail->plate,
                    'description' => 'Mensalidade de '
                      . $periodDetail->startedat . ' à '
                      . $periodDetail->endedat,
                    'value' => null
                  ];
                }
                $cols[$side][] = [
                  'plate' => null,
                  'description' => 'Total da mensalidade',
                  'value' => $billing->billingvalue
                ];
              } else {
                // Exibimos o detalhamento todo em uma linha
                $periodDetail = $periodDetails[0];
                $cols[$side][] = [
                  'plate' => $periodDetail->plate,
                  'description' => 'Mensalidade de '
                    . $ascertainedPeriod->startedat . ' à '
                    . $ascertainedPeriod->endedat,
                  'value' => $billing->billingvalue
                ];
              }
            } else {
              if ($billing->plate !== $lastPlate) {
                // Alternamos a coluna
                $side = ($side === 'left')
                  ? 'right'
                  : 'left'
                ;
              }

              $plate = ($lastPlate === $billing->plate)
                ? ''
                : $billing->plate
              ;

              $lastPlate = $billing->plate;

              $cols[$side][] = [
                'plate' => $plate,
                'description' => $billing->name,
                'value' => $billing->billingvalue
              ];
            }
          }
        }

        if (count($cols['left']) > 0) {
          // Adicionamos as colunas
          $details[] = [
            'type' => 'cols',
            'cols' => $cols,
            'contract' => $lastContractNumber
          ];
        }

        if ($payment->numberofparcels > 0) {
          // Obtemos o valor da parcela em float, pois o model retorna
          // ele formatado
          $fmt = numfmt_create( 'pt_BR', NumberFormatter::DECIMAL );
          $valueToPay = numfmt_parse($fmt, $payment->valuetopay);

          // Obtemos o valor total
          $fmt = numfmt_create( 'pt_BR', NumberFormatter::CURRENCY );
          $total = numfmt_format_currency(
            $fmt,
            ($payment->numberofparcels * $valueToPay),
            "BRL"
          );

          // Adicionamos a informação de parcelamento dos valores
          $details[] = [
            'type' => 'warning',
            'color' => 'graphite',
            'data' => 'TOTAL: ' . $total
              . '&nbsp;&nbsp;&nbsp;&ndash;&nbsp;&nbsp;&nbsp;'
              . 'Valor parcelado em ' . $payment->numberofparcels
              . ' vezes de R$ ' . $payment->valuetopay
          ];
          $details[] = [
            'type' => 'warning',
            'color' => 'ruby',
            'data' => '※ ※ ※ Neste documento está sendo cobrado a '
              . 'parcela ' . $payment->parcel . ' de '
              . $payment->numberofparcels . ' ※ ※ ※'
          ];
        }

        if ($payment->referencemonthyear) {
          // Inserimos a mensagem de alerta
          $details[] = [
            'type' => 'warning',
            'color' => 'orange',
            'data' => '❈❈❈ APÓS 30 DIAS DO VENCIMENTO O LOGIN E SENHA '
              . 'PODERÁ SER BLOQUEADO ❈❈❈'
          ];
        }

        // Adicionamos as informações no boleto sobre o que estamos
        // cobrando
        $billet
          ->setTotalizer($totalOfInstallations)
          ->setHistoric($details)
        ;
      }

      // Adicionamos o boleto gerado
      $billets[] = $billet;
    }

    return $billets;
  }

  /**
   * Gera um PDF para impressão das informações de uma cobrança.
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
  public function getPDF(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à geração de PDF com as informações de "
      . "uma cobrança."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    try {
      // Recupera as informações da cobrança
      $paymentID = $args['paymentID'];

      // Determina se o boleto é simples ou faz parte de um carnê
      $payment = Payment::join('invoices',
            'payments.invoiceid', '=', 'invoices.invoiceid'
          )
        ->where('payments.contractorid', '=', $contractorID)
        ->where('payments.paymentid', '=', $paymentID)
        ->get([
            'invoices.carnetid',
            'payments.paymentsituationid AS situation',
            'payments.paymentmethodid AS methodid'
          ])
        ->first()
      ;
      $carnetID = $payment->carnetid;

      if ($carnetID) {
        $documentID = $carnetID;
        $single = false;
      } else {
        $documentID = $paymentID;
        $single = true;
      }

      // Obtém o(s) boleto(s)
      $billets = [];
      if ($payment->methodid == 5) {
        $billets = $this->getBillets($documentID, $single);
      } else {
        // Alerta o usuário
        $this->flash("error", "Não é possível imprimir esta cobrança.");

        // Registra o evento
        $this->debug("Redirecionando para {routeName}",
          ['routeName' => 'ERP\Financial\Payments']
        );

        // Redireciona para a página de gerenciamento de cobranças
        return $this->redirect($response, 'ERP\Financial\Payments');
      }
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar a cobrança código "
        . "{paymentID}." . $exception->getMessage(),
        ['paymentID' => $paymentID]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta "
        . "cobrança."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Financial\Payments']
      );

      // Redireciona para a página de gerenciamento de cobranças
      return $this->redirect($response, 'ERP\Financial\Payments');
    }

    // Criamos o renderizador para o boleto em formato HTML
    $html = new HTML();

    // Definimos o layout
    if ($single) {
      $html
        ->setInvoiceLayout()
      ;
      $name = "Documento";
      $type = "Boleto";
    } else {
      $html
        ->setBookletLayout()
        ->printBookCover()
      ;
      $name = "Carnê";
      $type = "Carne";
    }

    $number = str_pad($documentID, 8, '0', STR_PAD_LEFT);
    $title = "{$name} nº {$number}";
    $PDFFileName = "{$type}_{$number}.pdf";

    // Adicionamos o(s) boleto(s)
    foreach ($billets as $billet) {
      $html
        ->addBillet($billet)
      ;
    }

    // Montamos os dados do boleto
    //$logo        = $this->getContractorLogo(
    //  $contractor->uuid, 'normal'
    //);
    $paidStamp   = $this->getPaidStamp();
    $content     = $html->render();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait', false));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion = true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Cobrança');
    $mpdf->SetCreator('TrackerERP');

    // Define os cabeçalhos e rodapés
    //$mpdf->SetHTMLHeader($header);
    //$mpdf->SetHTMLFooter($footer);

    // Seta modo tela cheia
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->showImageErrors = false;
    $mpdf->debug = false;

    // Inclui o conteúdo
    $mpdf->WriteHTML($content);

    if ($single) {
      // Verifica se o boleto já foi pago
      if ($payment->situation == PaymentSituation::PAIDED) {
        $mpdf->SetWatermarkImage($paidStamp);
        $mpdf->showWatermarkImage = true;
      }
    }

    // Envia o relatório para o browser no modo Inline
    $stream = fopen('php://memory', 'r+');
    ob_start();
    $mpdf->Output($PDFFileName, 'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    $this->info("Acesso ao PDF com os dados da cobrança '{invoiceID}'.",
      [ 'invoiceID' => $payment['invoiceid'] ]
    );

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader(
          'Cache-Control',
          'no-store, no-cache, must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader(
          'Last-Modified',
          gmdate('D, d M Y H:i:s') . 'GMT'
        )
    ;
  }

  /**
   * Formata um valor de data/hora recebida do banco de dados no padrão
   * para exibição no Excel.
   * 
   * @param string $sqlDateTime
   *   A data/hora no formato SQL
   *
   * @return string
   *   A data/hora formatada no padrão brasileiro (dd/mm/YYYY HH:ii:ss)
   */
  protected function formatDateTime(string $sqlDateTime): string
  {
    if (strpos($sqlDateTime, '.') !== false) {
      return Carbon::createFromFormat('Y-m-d\TH:i:s.u', $sqlDateTime)
        ->locale('pt_BR')
        ->format('d/m/Y H:i:s')
      ;
    }
    
    return Carbon::createFromFormat('Y-m-d\TH:i:s', $sqlDateTime)
      ->locale('pt_BR')
      ->format('d/m/Y H:i:s')
    ;
  }

  /**
   * Gera um arquivo em Excel com os contatos e respectivos telefones
   * para cobrança de valores atrasados.
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
  public function getBillingPhoneList(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à geração de arquivo Excel com as "
      . "informações de telefones."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;
    $uuid = $contractor->uuid;

    // Recupera as informações de parâmetros adicionais informados
    $parms = $request->getQueryParams();
    if (array_key_exists('type', $parms)) {
      $type = $parms['type'];
    } else {
      $type = 1;
    }
    if (array_key_exists('overdue', $parms)) {
      $overdue = $parms['overdue'];
    } else {
      $overdue = 'false';
    }
    if (array_key_exists('sentToDunningBureau', $parms)) {
      $sentToDunningBureau = $parms['sentToDunningBureau'];
    } else {
      $sentToDunningBureau = 'false';
    }
    if (array_key_exists('amount', $parms)) {
      $amount = $parms['amount'];
    } else {
      $amount = 0;
    }
    $phoneType = 0;

    try {
      // Recupera as informações para montar a planilha
      $sql = "SELECT name,
                     sequence,
                     phoneType,
                     phoneNumber,
                     comment,
                     complement
                FROM erp.getBillingPhoneList({$contractorID}, {$phoneType}, {$overdue}, {$sentToDunningBureau}, {$amount}, {$type});"
      ;
      $this->debug($sql);
      $contents = $this->DB->select($sql);

      // Montamos o conteúdo de nossa planílha
      $workSheet = [];
      switch ($type) {
        case 4:
          // Recupera os parâmetros de criptografia
          $settings = $this->container['settings']['encryption'];
          $encryptionKey = $settings['key'];
          $algorithm     = $settings['algorithm'];

          // Cria o codec para criptografia
          $codec = new URLCodec($encryptionKey, $algorithm);

          $uri = $request->getUri();
          $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

          // Montamos o cabeçalho
          $workSheet[] = [
            1 => 'Nome',
            'Telefone',
            'Link para download',
            'Linha digitável',
          ];

          // Inicializamos as variáveis de controle
          $lastPaymentID = 0;
          $linkToBillet  = '';
          $digitableLine = '';

          foreach ($contents as $row) {
            // Recuperamos o ID do pagamento
            $paymentID = intval($row->comment);

            // Verificamos se o ID do pagamento é diferente do último,
            // de forma a não gerarmos o link e a linha digitável a cada
            // iteração, já que eles podem se repetir em várias linhas
            if ($paymentID !== $lastPaymentID) {
              // Obtemos o link para o boleto
              $billetData = [
                'contractorID' => $contractorID,
                'uuid' => $uuid,
                'paymentID' => $paymentID
              ];
              $linkToBillet = sprintf('%s/%s',
                $baseUrl . '/usr/billet/get/pdf',
                urlencode(
                  $codec->encode($billetData)
                )
              );

              // Obtemos a linha digitável
              $payment = BankingBilletPayment::where('paymentid', '=', $paymentID)
                ->where('contractorid', '=', $contractorID)
                ->get([
                    $this->DB->raw(
                      "getDigitableLine(bankCode, agencyNumber, accountNumber, "
                        . "wallet, billingCounter, invoiceID, dueDate, "
                        . "valueToPay, parameters) AS digitableline"
                    )
                  ])
              ;
              $payment = $payment
                ->first()
              ;
              $digitableLine = $payment->digitableline;

              // Armazena o ID do último pagamento
              $lastPaymentID = $paymentID;
            }

            $workSheet[] = [
              1 => $row->name,
              (
                ($row->phonenumber)
                ? '55' . preg_replace('/[^0-9.]+/', '', $row->phonenumber)
                : ''
              ),
              (string) $linkToBillet,
              (string) $digitableLine
            ];
          }
          
          break;
        case 3:
          // Montamos o cabeçalho
          $workSheet[] = [
            1 => 'Nome',
            'Telefone',
            'Observação',
            'Placa',
            'Última comunicação'
          ];
          foreach ($contents as $row) {
            $complements = json_decode($row->complement);
            foreach ($complements AS $complement) {
              $workSheet[] = [
                1 => $row->name,
                (
                  ($row->phonenumber)
                  ? '55' . preg_replace('/[^0-9.]+/', '', $row->phonenumber)
                  : ''
                ),
                $row->comment,
                $complement->plate,
                $this->formatDateTime($complement->lastCommunication)
              ];
            }
          }
          
          break;
        case 6:
          // Montamos o cabeçalho
          $workSheet[] = [
            1 => 'Rastreador',
            'Telefone',
            'Observação',
            'ICCID',
            'Placa',
            'Última comunicação'
          ];
          foreach ($contents as $row) {
            $complements = json_decode($row->complement);
            foreach ($complements AS $complement) {
              $workSheet[] = [
                1 => $row->name,
                (
                  ($row->phonenumber)
                  ? '55' . preg_replace('/[^0-9.]+/', '', $row->phonenumber)
                  : ''
                ),
                $row->comment,
                $complement->iccid,
                $complement->plate,
                $this->formatDateTime($complement->lastCommunication)
              ];
            }
          }
          
          break;
        default:
          // Montamos o cabeçalho
          $workSheet[] = [
            1 => 'Nome',
            'Telefone',
            'Observação'
          ];
          foreach ($contents as $row) {
            $workSheet[] = [
              1 => $row->name,
              (
                ($row->phonenumber)
                ? '55' . preg_replace('/[^0-9.]+/', '', $row->phonenumber)
                : ''
              ),
              $row->comment
            ];
          }
          
          break;
      }

      // Exportamos para Excel
      $xls = new Excel_XML;
      $xls->addWorksheet('Lista', $workSheet);
      $workbook = $xls->getWorkbook();
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar as informações de "
        . "contato dos clientes com faturas em atraso.",
        [ ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar os dados de "
        . "contato."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Financial\Payments']
      );

      // Redireciona para a página de gerenciamento de cobranças
      return $this->redirect($response, 'ERP\Financial\Payments');
    }

    // Envia o relatório para o browser no modo Inline
    $stream = fopen('php://memory', 'r+');
    ob_start();
    echo $workbook;
    $excelData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $excelData);
    rewind($stream);

    return $response
      ->withBody(new Stream($stream))
      ->withHeader(
          'Content-Type', 'application/vnd.ms-excel; charset=UTF-8'
        )
      ->withHeader(
          'Content-Disposition', 'inline; filename=phonelist.xls'
        )
      ->withHeader(
          'Cache-Control', 'no-store, no-cache, must-revalidate'
        )
      ->withHeader(
          'Expires', 'Sun, 1 Jan 2000 12:00:00 GMT'
        )
      ->withHeader(
          'Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT'
        )
    ;
  }

  /**
   * Recupera as informações da linha digitável de um boleto.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getDigitableLine(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $paymentID = $postParams['paymentID'];

    // Registra o acesso
    $this->debug(
      "Requisitando informações da linha digitável do boleto do "
        . "pagamento {paymentID}",
      ['paymentID' => $paymentID]
    );

    $payment = BankingBilletPayment::where('paymentid', '=', $paymentID)
      ->where('contractorid', '=', $contractorID)
      ->get([
          $this->DB->raw(
            "getDigitableLine(bankCode, agencyNumber, accountNumber, "
              . "wallet, billingCounter, invoiceID, dueDate, "
              . "valueToPay, parameters) AS digitableline"
          )
        ])
    ;

    if ($payment->isEmpty()) {
      // Retorna o erro
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => "Não foi possível obter a linha digitável do "
              . "pagamento nº {$paymentID}",
            'data' => null
          ])
      ;
    }

    $payment = $payment
      ->first()
    ;

    // Retorna a linha digitável
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'OK',
          'params' => $request->getQueryParams(),
          'message' => "Obtida a inha digitável do pagamento nº {$paymentID}",
          'data' => $payment->digitableline
        ])
    ;
  }

  /**
   * Recupera as informações do link para download de um boleto.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getDownloadableLink(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;
    $uuid = $contractor->uuid;

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $paymentID = $postParams['paymentID'];

    // Registra o acesso
    $this->debug(
      "Requisitando informações do link para download do boleto do "
        . "pagamento {paymentID}",
      ['paymentID' => $paymentID]
    );

    // Recupera os parâmetros de criptografia
    $settings = $this->container['settings']['encryption'];
    $encryptionKey = $settings['key'];
    $algorithm     = $settings['algorithm'];

    // Cria o codec para criptografia
    $codec = new URLCodec($encryptionKey, $algorithm);

    $uri = $request->getUri();
    $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

    // Obtemos o link para o boleto
    $billetData = [
      'contractorID' => $contractorID,
      'uuid' => $uuid,
      'paymentID' => $paymentID
    ];
    $linkToBillet = sprintf('%s/%s',
      $baseUrl . '/usr/billet/get/pdf',
      urlencode(
        $codec->encode($billetData)
      )
    );

    // Retorna o link para download
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'OK',
          'params' => $request->getQueryParams(),
          'message' => "Obtida a inha digitável do pagamento nº {$paymentID}",
          'data' => $linkToBillet
        ])
    ;
  }

  /**
   * Envia a cobrança por e-mail. Se a cobrança está sendo realizada por
   * boleto bancário, anexa o conteúdo do boleto no corpo do e-mail.
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
  public function sendByMail(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Obtém os parâmetros
    $paymentID = $postParams['paymentID'];
    $customerID = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $paymentMethodID = $postParams['paymentMethodID'];
    $paymentMethodName = $postParams['paymentMethodName'];

    try {
      // Por padrão, não define nenhum callback para anexar dados ao
      // e-mail a ser enviado
      $callback = null;

      // Conforme o meio de pagamento, determina a ação necessária
      switch ($paymentMethodID) {
        case 1:
          // Dinheiro
          $documentSent = 'o recibo da cobrança';

          break;
        case 2:
          // Cheque
          $documentSent = 'o recibo da cobrança por cheque';

          break;
        case 3:
          // Cartão de débito
          $documentSent = 'o recibo da cobrança por cartão de débito';

          break;
        case 4:
          // Cartão de crédito
          $documentSent = 'o recibo da cobrança por cartão de crédito';

          break;
        case 5:
          // Boleto bancário

          // Registra o acesso
          $this->debug(
            "Solicitando o envio do boleto de cobrança para o "
              . "pagamento {paymentID} por e-mail",
            ['paymentID' => $paymentID]
          );

          // Insere o pagamento na fila
          $sql = ""
            . "INSERT INTO erp.emailsQueue"
            . "       (contractorID, mailEventID, originRecordID, recordsOnScope) VALUES"
            . "       ({$contractorID}, " . self::BILLET_SUBMISSION . ", {$paymentID}, erp.getPaymentScope({$paymentID}));"
          ;
          $this->DB->select($sql);

          // Retorna a informação do sucesso no registro
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Solicitado envio de e-mail com o boleto "
                . "de cobrança",
              'data' => NULL
            ]);

          break;
        case 6:
          // Transferência bancária
          $documentSent = 'as informações para pagamento por '
            . 'transferência bancária'
          ;

          break;

        default:
          // Tipo de meio de pagamento inválido ou não configurado
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'NOK',
                'params' => $request->getQueryParams(),
                'message' => "Não foi possível enviar e-mail. Meio de "
                  . "pagamento inválido",
                'data' => NULL
              ])
          ;

          break;
      }

      // Retorna a informação do erro no envio
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => "Não foi possível enviar e-mail com "
              . $documentSent,
            'data' => NULL
          ])
      ;
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível localizar a cobrança código {paymentID}.",
        [ 'paymentID' => $paymentID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta cobrança.");

      // Registra o evento
      $this->debug(
        "Redirecionando para {routeName}",
        ['routeName' => 'ERP\Financial\Payments']
      );

      // Redireciona para a página de gerenciamento de cobranças
      return $this->redirect($response, 'ERP\Financial\Payments');
    }
  }

  /**
   * Recupera as informações dos e-mails enviados em relação ao um
   * determinado pagamento.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getMailData(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $paymentID = $postParams['paymentID'];

    // Registra o acesso
    $this->debug(
      "Requisitando informações de e-mails enviados relativos ao "
        . "pagamento {paymentID}",
      [ 'paymentID' => $paymentID ]
    );

    try {
      $emails = EmailQueue::join('mailevents', 'emailsqueue.maileventid',
            '=', 'mailevents.maileventid'
          )
        ->where('emailsqueue.contractorid', '=', $contractor->id)
        ->whereRaw("emailsqueue.recordsonscope @> '{{$paymentID}}'")
        ->orderBy('emailsqueue.statusat')
        ->get([
            'emailsqueue.*',
            'mailevents.name AS maileventname'
          ])
      ;
      if (!$emails->isEmpty()) {
        // Retorna a relação de e-mails
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Obtido os e-mails enviados para o "
                . "pagamento nº {$paymentID}",
              'data' => $emails
            ])
        ;
      } else {
        $error = "Não foi possível obter os e-mails enviados do "
          . "pagamento nº {$paymentID}"
        ;
      }
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno no banco de dados: {error}.",
        [
          'module' => 'e-mails enviados',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de e-mails "
        . "enviados. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno: {error}.",
        [
          'module' => 'e-mails enviados',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de e-mails "
        . "enviados. Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => null
        ])
    ;
  }

  /**
   * Recupera as informações das tarifas cobradas em relação ao um
   * determinado pagamento.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getTariffData(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $paymentID = $postParams['paymentID'];

    // Registra o acesso
    $this->debug(
      "Requisitando informações de tarifas cobradas relativas ao "
        . "pagamento {paymentID}",
      [ 'paymentID' => $paymentID ]
    );

    try {
      $billetOccurrences = BankingBilletOccurrence::join(
            'occurrencetypes', 'bankingbilletoccurrences.occurrencetypeid',
            '=', 'occurrencetypes.occurrencetypeid'
          )
        ->where(
            'bankingbilletoccurrences.contractorid', '=',
            $contractor->id
          )
        ->where('bankingbilletoccurrences.tariffvalue', '>', 0.00)
        ->where('bankingbilletoccurrences.paymentid', '=', $paymentID)
        ->orderBy('bankingbilletoccurrences.occurrencedate')
        ->get([
            'bankingbilletoccurrences.occurrencedate',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN bankingbilletoccurrences.occurrencetypeid = 3 THEN 'Tarifa de registro'"
              . "  ELSE bankingbilletoccurrences.reasons "
              . "END as reasons"
            ),
            'bankingbilletoccurrences.tariffvalue'
          ])
      ;

      if (!$billetOccurrences->isEmpty()) {
        // Retorna a relação de e-mails
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Obtido as tarifas cobradas para o "
                . "pagamento nº {$paymentID}",
              'data' => $billetOccurrences
            ])
        ;
      }

      $error = "Não foi possível obter as tarifas cobradas do "
        . "pagamento nº {$paymentID}"
      ;
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno no banco de dados: {error}.",
        [
          'module' => 'tarifas cobradas',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de tarifas "
        . "cobradas. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno: {error}.",
        [
          'module' => 'tarifas cobradas',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de tarifas "
        . "cobradas. Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => null
        ])
    ;
  }

  /**
   * Recupera as informações do histórico de movimentos em relação ao um
   * determinado pagamento.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getHistoryData(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $paymentID = $postParams['paymentID'];

    // Registra o acesso
    $this->debug(
      "Requisitando informações de histórico de movimentos relativos "
        . "ao pagamento {paymentID}",
      [ 'paymentID' => $paymentID ]
    );

    try {
      // Recuperamos as informações de histórico de pagamentos
      $sql = "
        SELECT occurrenceID,
               to_char(eventDate, 'DD/MM/YYYY') AS eventDate,
               eventTypeID,
               eventTypeName,
               description,
               reasons,
               performed
          FROM erp.getPaymentHistory({$contractor->id}, {$paymentID});"
      ;
      $history = $this->DB->select($sql);

      if ($history) {
        // Retorna a relação de histórico de movimentos
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Obtido o histórico de movimentos para o "
                . "pagamento nº {$paymentID}",
              'data' => $history
            ])
        ;
      }

      $error = "Não foi possível obter o histórico de movimentos do "
        . "pagamento nº {$paymentID}"
      ;
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno no banco de dados: {error}.",
        [
          'module' => 'histórico de movimentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimentos. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno: {error}.",
        [
          'module' => 'histórico de movimento',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimento. Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => null
        ])
    ;
  }
}
