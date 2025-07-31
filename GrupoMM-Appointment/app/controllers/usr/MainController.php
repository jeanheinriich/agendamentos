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
 * O controlador da página inicial do aplicativo de ERP de controle de
 * rastreadores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\USR;

use App\Models\AscertainedPeriod;
use App\Models\AscertainedPeriodDetail;
use App\Models\BankingBilletPayment;
use App\Models\Entity as Contractor;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Payments\AgentEntity;
use Core\Payments\BankingBillet\BankingBilletFactory;
use Core\Payments\BankingBillet\Renderer\HTML;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mpdf\Mpdf;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class MainController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos.
   */
  use HandleFileTrait;

  /**
   * Exibe a página inicial da área do cliente.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function home(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Recuperamos o código do cliente
    $customerID = $this->authorization->getUser()->entityid;
    $sql = "SELECT DISTINCT ON (subsidiary.subsidiaryid)
                   customer.name,
                   customerType.juridicalperson,
                   subsidiary.nationalregister
              FROM erp.entities AS customer
        INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
        INNER JOIN erp.subsidiaries AS subsidiary ON (customer.entityID = subsidiary.entityID)
             WHERE customer.entityID = {$customerID}
             ORDER BY subsidiary.subsidiaryid;";
    $customers = $this->DB->select($sql);
    $customer = $customers[0];

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );

    // Registra o acesso
    $this->debug("Acesso à página inicial.");

    //$this->DB->select("SET lc_monetary = 'pt_BR.UTF8';");
    $sql = "SELECT P.paymentID AS id,
                   P.invoiceID,
                   P.referenceMonthYear,
                   P.dueDate,
                   to_char(P.dueDate, 'DD/MM/YYYY') AS dueDate,
                   CASE
                     WHEN P.dueDate > CURRENT_DATE THEN DATE_PART('day', P.dueDate::timestamp - CURRENT_TIMESTAMP) + 1
                     WHEN P.dueDate = CURRENT_DATE THEN 0
                     ELSE DATE_PART('day', CURRENT_TIMESTAMP - P.dueDate::timestamp) * -1
                   END AS diffInDays,
                   REPLACE(REPLACE(REPLACE(REPLACE(P.valueToPay::money::text,'$','R$ '),',','|'),'.',','),'|','.') AS valueToPay,
                   P.overdue,
                   P.paymentMethodID,
                   CASE
                     WHEN P.paymentMethodID = 5 THEN 'boleto'
                     ELSE 'documento'
                   END AS methodname,
                   P.paymentMethodName,
                   P.paymentSituationID,
                   P.paymentSituationName,
                   P.droppedTypeID,
                   P.droppedTypeName,
                   P.digitableline,
                   to_char(P.paidDate, 'DD/MM/YYYY') AS paidDate,
                   REPLACE(REPLACE(REPLACE(REPLACE(P.paidValue::money::text,'$','R$ '),',','|'),'.',','),'|','.') AS paidValue,
                   CASE
                     WHEN P.latePaymentInterest > 0.00 AND P.fineValue > 0 THEN 1
                     WHEN P.fineValue > 0 THEN 2
                     WHEN P.latePaymentInterest > 0.00 THEN 3
                     ELSE 0
                   END AS additionals,
                   REPLACE(REPLACE(REPLACE(REPLACE(P.latePaymentInterest::float8::numeric::money::text,'$','R$ '),',','|'),'.',','),'|','.') AS latePaymentInterest,
                   REPLACE(REPLACE(REPLACE(REPLACE(P.fineValue::money::text,'$','R$ '),',','|'),'.',','),'|','.') AS fineValue,
                   CASE
                     WHEN P.abatementValue > 0.00 THEN TRUE
                     ELSE FALSE
                   END AS discountgranted,
                   REPLACE(REPLACE(REPLACE(REPLACE(P.abatementValue::float8::numeric::money::text,'$','R$ '),',','|'),'.',','),'|','.') AS abatementValue,
                   P.fullcount
              FROM erp.getPaymentsData({$contractor->id}, {$customerID},
                NULL, NULL, NULL, NULL, NULL, 'payments.dueDate DESC', 0, 0) AS P;"
    ;
    $payments = $this->DB->select($sql);

    // Renderiza a página
    return $this->render($request, $response,
        'usr/home.twig',
        [
          'customer' => $customer,
          'payments' => $payments
        ]
    );
  }

  /**
   * Exibe a página de apresentação do sistema de ERP.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function about(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Sobre',
      $this->path('USR\About')
    );

    // Registra o acesso
    $this->debug("Acesso à página sobre.");
    
    // Renderiza a página
    return $this->render($request, $response, 'usr/about.twig');
  }
  
  /**
   * Exibe a página de controle de privacidade.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function privacity(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('USR\Home')
    );
    $this->breadcrumb->push('Política de privacidade',
      $this->path('USR\Privacity')
    );

    // Registra o acesso
    $this->debug("Acesso à página de controle de privacidade.");
    
    // Renderiza a página
    return $this->render($request, $response, 'usr/privacity.twig');
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

    try {
      // Recupera as informações da cobrança
      $paymentID = $args['paymentID'];

      // Determina se o boleto é simples ou faz parte de um carnê
      $payment = BankingBilletPayment::join('invoices',
            'bankingbilletpayments.invoiceid', '=', 'invoices.invoiceid'
          )
        ->where('bankingbilletpayments.contractorid', '=', $contractor->id)
        ->where('bankingbilletpayments.paymentid', '=', $paymentID)
        ->get(['invoices.carnetid'])
        ->first()
      ;
      $carnetID = $payment->carnetid;

      $hasCarnet = ($carnetID > 0) ? true : false;

      // Obtém o(s) boleto(s)
      $billets = $this->getBillet($paymentID, $hasCarnet);
    } catch (ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar a cobrança código "
        . "{paymentID}.",
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
    if ($hasCarnet) {
      $html
        ->setBookletLayout()
      ;
      $name = "Carnê";
      $type = "Carne";
    } else {
      $html
        ->setInvoiceLayout()
      ;
      $name = "Documento";
      $type = "Boleto";
    }

    $number = str_pad($paymentID, 8, '0', STR_PAD_LEFT);
    $title = "{$name} nº {$number}";
    $PDFFileName = "{$type}_{$number}.pdf";

    // Adicionamos o(s) boleto(s)
    foreach ($billets as $billet) {
      $html
        ->addBillet($billet)
      ;
    }

    // Montamos os dados do boleto
    $content = $html->render();

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
   * Obtém o boleto em função do documento informado.
   *
   * @param int $paymentID
   *   O ID do pagamento
   * @param bool $hasCarnet
   *   O indicativo de que a cobrança é na forma de carnê
   *
   * @return array
   *   A matriz com os dados boleto
   */
  protected function getBillet(
    int $paymentID,
    bool $hasCarnet
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

    // Obtemos as informações da cobrança
    $payments = BankingBilletPayment::join('invoices',
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
      ->join('definedmethods', 'invoices.definedmethodid', '=',
          'definedmethods.definedmethodid'
        )
      ->where('bankingbilletpayments.contractorid', '=', $contractorID)
      ->where('bankingbilletpayments.paymentid', '=', $paymentID)
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
          'paymentmethods.name AS paymentmethodname',
          'definedmethods.parameters'
        ])
    ;

    if ($payments->isEmpty()) {
      throw new ModelNotFoundException("Não temos nenhuma cobrança "
        . "com o código {$paymentID} cadastrada"
      );
    }

    // Obtém as configurações do boleto
    $parameters = json_decode($payments[0]->parameters);

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
        ->setCIP($parameters->CIP)
        // Informações do documento
        ->setDateOfDocument(
            Carbon::createFromFormat('Y-m-d', $payment->invoicedate)
              ->locale('pt_BR')
          )
        ->setKindOfDocument($parameters->kindOfDocument)
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

      if (! $hasCarnet) {
        // Recuperamos as informações dos valores cobrados
        $invoiceID = $payment->invoiceid;

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

        // Para cada cobrança, montamos as informações do detalhamento
        $details = [];
        $lastCustomerID = $payment->customerid;
        $lastContractID = 0;
        $lastContractNumber = '';
        $cols = [
          'left' => [],
          'right' => [],
          'amount' => 0
        ];
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
        if (count($cols['left']) > 0) {
          // Adicionamos as colunas
          $details[] = [
            'type' => 'cols',
            'cols' => $cols,
            'contract' => $lastContractNumber
          ];
        }

        if ($payment->referencemonthyear) {
          // Inserimos a mensagem de alerta
          $details[] = [
            'type' => 'warning',
            'name' => '❈❈❈ APÓS 30 DIAS DO VENCIMENTO O LOGIN E SENHA PODERÁ SER BLOQUEADO ❈❈❈'
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
}
