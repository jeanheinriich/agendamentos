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
 * O controlador do gerenciamento de arquivos de remessa e retorno no
 * padrão CNAB.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\BankingBilletPayment;
use App\Models\BankingTransmissionFile;
use App\Models\DefinedMethod;
use App\Models\Entity AS Contractor;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Payments\AgentEntity;
use Core\Payments\BankingBillet\BankingBilletFactory;
use Core\Payments\Cnab\BilletOccurrence;
use Core\Payments\Cnab\BilletStatus;
use Core\Payments\Cnab\Returning\ReturnFileFactory;
use Core\Payments\Cnab\Shipping\Cnab400\Bradesco AS ShippingFile;
use Core\Payments\PaymentRestriction;
use Core\Payments\PaymentSituation;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class CnabController
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
   * Obtém o ano e o mês atual para fins de armazenamento.
   * 
   * @return string
   */
  private function getYearAndMonth():string
  {
    $today = Carbon::now();

    return $today
      ->format('Y/m')
    ;
  }

  /**
   * Recupera as informações do emissor.
   * 
   * @param int $contractorID
   *   O ID do contratante
   *
   * @return Contractor
   *   As informações do emissor
   *
   * @throws RuntimeException
   *   Em caso de não termos emissor cadastrado
   */
  protected function getEmitter(int $contractorID): Contractor
  {
    try {
      // Recuperamos os dados do emissor
      $emitter = Contractor::join("entitiestypes",
            "entities.entitytypeid", '=', "entitiestypes.entitytypeid"
          )
        ->join('subsidiaries',
            'entities.entityid', '=', 'subsidiaries.entityid'
          )
        ->join('documenttypes', 'subsidiaries.regionaldocumenttype',
            '=', 'documenttypes.documenttypeid'
          )
        ->join('cities', 'subsidiaries.cityid',
            '=', 'cities.cityid'
          )
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
      ;

      if ( $emitter->isEmpty() ) {
        throw new Exception("Não temos nenhum emissor cadastrado com o "
          . "código {$contractorID}"
        );
      }

      $emitter = $emitter
        ->first()
      ;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações do emissor. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os dados do "
        . "emissor"
      );
    }

    return $emitter;
  }

  /**
   * Recupera as configurações dos boletos definidos.
   * 
   * @param int $contractorID
   *   O ID do contratante
   *
   * @return Collection
   *   A matriz com as configurações de boletos definidas
   *
   * @throws RuntimeException
   *   Em caso de não termos configurações de boletos definidas
   */
  protected function getDefinedBankingBillets(int $contractorID): Collection
  {
    try {
      // Recuperamos os boletos definidos
      $definedMethods = DefinedMethod::join("accounts",
            "definedmethods.accountid", "=", "accounts.accountid"
          )
        ->where("definedmethods.contractorid", '=', $contractorID)
        ->where('definedmethods.paymentmethodid', '=', 5)
        ->get([
            'definedmethods.*',
            'accounts.*'
          ])
      ;

      if ( $definedMethods->isEmpty() ) {
        throw new Exception("Não temos nenhum boleto configurado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações da "
        . "configuração do boleto. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as "
        . "configurações do boleto definido"
      );
    }

    return $definedMethods;
  }

  /* =====================================[ Arquivo de Remessa ]===== */

  /**
   * Exibe a página inicial do gerenciamento de arquivos de remessa.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function shippingFile(Request $request, Response $response)
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
    $this->breadcrumb->push('CNAB', '');
    $this->breadcrumb->push(
      'Arquivos de remessa',
      $this->path('ERP\Financial\Payments\CNAB\ShippingFile')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de arquivos de remessa.");

    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/payments/cnab/shippingfile.twig', [ ]
    );
  }

  /**
   * Recupera a relação dos arquivos de remessa gerados no formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getShippingFiles(Request $request, Response $response)
  {
    $this->debug(
      "Acesso à relação de arquivos de remessa."
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

    try {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Realiza a consulta
      $contractorID = $this->authorization->getContractor()->id;

      $sql = "SELECT * FROM ;"
      ;
      $shippingFiles = $this->DB->select($sql);

      if (count($shippingFiles) > 0) {
        $rowCount = $shippingFiles[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $shippingFiles
            ])
        ;
      } else {
        $error = "Não temos arquivos de remessa gerados.";
      }
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [
          'module' => 'arquivos de remessa',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de arquivos "
        . "de remessa. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [
          'module' => 'arquivos de remessa',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de arquivos "
        . "de remessa. Erro interno."
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
   * Recupera o arquivo de remessa. Se informado um ID, apenas recupera
   * o arquivo. Caso contrário, gera um novo arquivo
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
  public function getShippingFile(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recuperamos a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Obtém o ID do arquivo (se informado)
    $transmissionFileID = array_key_exists('fileID', $args)
      ? $args['fileID']
      : 0
    ;

    try {
      // Recupera o local de armazenamento dos arquivos de remessa
      $conciliationsPath = ''
        . $this->container['settings']['storage']['conciliations']
        . DIRECTORY_SEPARATOR . $contractor->id
      ;

      // Verifica se o destino é um diretório válido
      if (is_dir($conciliationsPath)) {
        if (!is_writable($conciliationsPath)) {
          throw new RuntimeException(
            "O caminho de destino dos arquivos de conciliação bancária "
            . "não é gravável. " . $conciliationsPath
          );
        }
      } else {
        // Verifica se podemos criar o diretório corretamente
        if (false === @mkdir($conciliationsPath, 0777, true)) {
          // Limpamos o cache
          clearstatcache(true, $conciliationsPath);

          // Verificamos novamente se o diretório existe
          if (!is_dir($conciliationsPath)) {
            throw new RuntimeException(
              "Não é possível criar o caminho de destino dos arquivos "
              . "de conciliação bancária"
            );
          }
        }
      }
    } catch (RuntimeException $exception)
    {
      // Retornamos um erro como resposta
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(404)
        ->withJson([
            'result' => 'NOK',
            'params' => $args,
            'message' => "Não foi possível obter o arquivo de "
              . "remessa. " . $exception->getMessage(),
            'data' => null
          ])
      ;
    }
  
    if ($transmissionFileID == 0) {
      // Não foi informado um arquivo de remessa, então gera um novo
      // arquivo de remessa se houverem instruções pendentes a serem
      // transmitidas à instituição financeira

      try {
        // Obtemos os dados do emissor que é o contratante
        $emitter = $this->getEmitter($contractor->id);

        // Obtemos os dados do banco para o qual iremos gerar o arquivo
        // de remessa
        $definedBillets = $this->getDefinedBankingBillets($contractor->id);
        if (count($definedBillets) > 1) {
          // Registra o erro
          $this->error("Existe mais de um banco emissor configurado");

          throw new RuntimeException(
            "Existe mais de um banco emissor configurado"
          );
        }
        $definedBillet = $definedBillets[0];

        // Obtém as configurações do boleto
        $parameters = json_decode($definedBillet->parameters);

        // Criamos o agente emissor (Beneficiário). No nosso caso, o
        // agente garantidor é a mesma entidade que o agente emissor
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
          // Os dados da conta bancária
          ->setAgencyNumber($definedBillet->agencynumber)
          ->setAccountNumber($definedBillet->accountnumber)
        ;
      } catch (RuntimeException $exception)
      {
        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível obter o arquivo de "
                . "remessa. " . $exception->getMessage(),
              'data' => null
            ])
        ;
      }

      try {
        // Iniciamos nossa consulta para obter as informações de
        // instruções que precisem ser enviadas ao banco
        $payments = BankingBilletPayment::join('invoices',
              'bankingbilletpayments.invoiceid', '=', 'invoices.invoiceid'
            )
          ->join('entities as customers',
              'invoices.customerid', '=', 'customers.entityid'
            )
          ->join("entitiestypes", "customers.entitytypeid",
              '=', "entitiestypes.entitytypeid"
            )
          ->join('subsidiaries',
              'invoices.subsidiaryid', '=', 'subsidiaries.subsidiaryid'
            )
          ->join('documenttypes', 'subsidiaries.regionaldocumenttype',
              '=','documenttypes.documenttypeid'
            )
          ->join('cities', 'subsidiaries.cityid', '=', 'cities.cityid')
          ->join('paymentmethods', 'bankingbilletpayments.paymentmethodid', '=',
              'paymentmethods.paymentmethodid'
            )
          ->join('billetdispatching', 'bankingbilletpayments.paymentid',
              '=', 'billetdispatching.paymentid'
            )
          ->join('billetinstructions', 'billetdispatching.instructionid',
              '=', 'billetinstructions.instructionid'
            )
          ->where('bankingbilletpayments.definedmethodid', '=', $definedBillet->definedmethodid)
          ->where('bankingbilletpayments.valuetopay', '>', 0.00)
          ->whereNull('billetdispatching.shippingfileid')
          ->get([
              'bankingbilletpayments.*',
              'billetinstructions.instructioncode',
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
              'invoices.referencemonthyear',
              'invoices.invoicedate',
              'paymentmethods.name AS paymentmethodname'
            ])
        ;

        if ( $payments->isEmpty() ) {
          // Retornamos um erro como resposta para informar que não
          // temos registros a serem enviados
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(406)
            ->withJson([
                'result' => 'NOK',
                'params' => $args,
                'message' => "Não temos instruções pendentes a serem "
                  . "transmitidas à instituição financeira",
                'data' => null
              ])
          ;
        }

        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Atualizamos o contador de arquivos de remessa emitidos
        $sql = "UPDATE erp.definedMethods
                   SET shippingCounter = shippingCounter + 1 
                 WHERE definedMethodID = {$definedBillet->definedmethodid}
             RETURNING shippingCounter;"
        ;
        $shipping = $this->DB->select($sql);
        $shippingCounter = $shipping[0]->shippingcounter;

        // Atualizamos o contador de arquivos de remessa emitidos no
        // mesmo dia
        $sql = "UPDATE erp.definedMethods
                   SET dayCounter = CASE WHEN counterDate = CURRENT_DATE THEN dayCounter + 1 ELSE 1 END,
                       counterDate = CURRENT_DATE
                 WHERE definedMethodID = {$definedBillet->definedmethodid}
             RETURNING dayCounter;"
        ;
        $shipping = $this->DB->select($sql);
        $dayCounter = $shipping[0]->daycounter;

        // Percorremos os pagamentos para gerar os boletos
        $billets = [];
        foreach ($payments AS $payment) {
          // Criamos o agente pagador
          $payerAgent = (new AgentEntity())
            ->setName($payment->customername)
            ->setDocumentNumber($payment->nationalregister)
            ->setAddress($payment->address)
            ->setStreetNumber($payment->streetnumber)
            ->setComplement($payment->complement)
            ->setDistrict($payment->district)
            ->setPostalCode($payment->postalcode)
            ->setCity($payment->cityname)
            ->setState($payment->state)
          ;

          // Criamos o boleto
          $billet = (BankingBilletFactory::loadBankFromCode(intval($payment->bankcode)))
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
            ->setDateOfExpiration($payment->duedate)
            // O valor da multa
            ->setFineValue(floatval($payment->finevalue))
            // O valor dos juros de mora
            ->setArrearInterestType($payment->arrearinteresttype)
            ->setArrearInterestPerDay(floatval($payment->arrearinterest))
            // Ação após o vencimento: Negativar o título após 30 dias
            ->setInstructionAfterExpiration($payment->instructionid, $payment->instructiondays)
            ->setAutoInstructionsText()
            ->setReferenceMonth($payment->referencemonthyear)
            ->setBilletInstruction($payment->instructioncode)
          ;

          // Adicionamos o boleto gerado
          $billets[] = $billet;
        }

        // Geramos o arquivo de remessa
        $shippingFile = (new ShippingFile())
          ->setWallet($payment->wallet)
          ->setEmitter($emitterAgent)
          ->setEmitterCode("{$parameters->emitterCode}")
          ->setSequentialShippingNumber($shippingCounter)
          // Repetimos a quantidade de vezes necessárias e/ou usamos uma
          // matriz que contenha todos os boletos e adicionamos de uma vez
          ->addBillets($billets)
        ;

        // Obtém o local para armazenamento do arquivo de remessa
        $targetPath = $conciliationsPath
          . DIRECTORY_SEPARATOR . $this->getYearAndMonth()
        ;
        if (!file_exists($targetPath)) {
          // Verifica se podemos criar o diretório de destino
          // corretamente
          if (false === @mkdir($targetPath, 0777, true)) {
            // Limpamos o cache
            clearstatcache(true, $targetPath);

            // Verificamos novamente se o diretório existe
            if (!is_dir($targetPath)) {
              throw new RuntimeException(
                "Não é possível criar o local para armazenamento do "
                . "arquivo de remessa"
              );
            }
          }
        }

        $filename = $shippingFile->save($targetPath, $dayCounter);

        // Criamos um novo registro do arquivo de remessa
        $transmissionFile = new BankingTransmissionFile();
        $transmissionFile->contractorid = $contractor->id;
        $transmissionFile->filename = $this->getYearAndMonth()
          . DIRECTORY_SEPARATOR . $filename
        ;
        $transmissionFile->isshippingfile = true;
        $transmissionFile->save();
        $transmissionFileID = $transmissionFile->transmissionfileid;

        // Registra o número do arquivo de remessa e a data de envio das
        // instruções dos boletos
        $sql = "UPDATE erp.billetDispatching
                   SET shippingFileID = {$transmissionFileID},
                       dispatchDate = CURRENT_DATE
                 WHERE shippingFileID IS NULL
                   AND contractorid = {$contractor->id};"
        ;
        $this->DB->select($sql);
          
        // Efetiva a transação
        $this->DB->commit();

        $this->info(
          "Gerado arquivo de remessa {name}",
          [ 'name' => $filename ]
        );
      } catch (QueryException $exception) {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível obter o arquivo de "
                . "remessa. " . $exception->getMessage(),
              'data' => null
            ])
        ;
      } catch (RuntimeException $exception) {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível obter o arquivo de "
                . "remessa. " . $exception->getMessage(),
              'data' => null
            ])
        ;
      }
    }

    // ---------------[ Recupera o arquivo de remessa requisitado ]-----

    $this->debug(
      "Acesso a obtenção de arquivo de remessa ID {fileID}.",
      [ 'fileID' => $transmissionFileID ]
    );

    try {
      // Obtém os dados do arquivo de remessa
      $transmissionFile = BankingTransmissionFile::findOrFail($transmissionFileID);

      // Obtém o nome do arquivo de remessa
      $cnabFile = $conciliationsPath
        . DIRECTORY_SEPARATOR . $transmissionFile->filename
      ;

      if (file_exists($cnabFile)) {
        // Obtém os dados do arquivo
        $fileName = basename($transmissionFile->filename);
        $fileSize = filesize($cnabFile);
        $mimeType = "application/octet-stream";
        $fileHandle = fopen($cnabFile, 'rb');
        $stream = new Stream($fileHandle);

        // Determina o tempo de cache para 7 dias
        $maxAge = 60*60*24*7;

        $this->debug("Obtento arquivo de remessa {$fileName}.");

        // Retorna o arquivo de remessa
        return $response
          ->withBody($stream)
          ->withHeader('Content-Type', $mimeType)
          ->withHeader('Content-Length', $fileSize)
          ->withHeader('Content-Disposition', "name='{$fileName}'")
          ->withHeader('Cache-Control', "max-age={$maxAge}")
          ->withHeader('Expires', gmdate(DATE_RFC1123, time() + $maxAge))
          ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s',
              filemtime($cnabFile)) . 'GMT'
            )
        ;
      } else {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível obter o arquivo de "
                . "remessa. Arquivo não encontrado.",
              'data' => null
            ])
        ;
      }
    } catch(ModelNotFoundException $exception) {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(406)
        ->withJson([
            'result' => 'NOK',
            'params' => $args,
            'message' => "Não foi possível obter o arquivo de "
              . "remessa. Erro: " . $exception->getMessage(),
            'data' => null
          ])
      ;
    }
  }

  /* =====================================[ Arquivo de Retorno ]===== */

  /**
   * Exibe a página inicial do gerenciamento de arquivos de retorno.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function returnFile(Request $request, Response $response)
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
    $this->breadcrumb->push('CNAB', '');
    $this->breadcrumb->push(
      'Arquivos de retorno',
      $this->path('ERP\Financial\Payments\CNAB\ReturnFile')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de arquivos de retorno.");

    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/payments/cnab/returnfile.twig', [ ]
    );
  }

  /**
   * Recupera a relação dos arquivos de retorno processados no formato
   * JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getReturnFiles(Request $request, Response $response)
  {
    $this->debug(
      "Acesso à relação de arquivos de retorno."
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

    try {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Realiza a consulta
      $contractorID = $this->authorization->getContractor()->id;

      $sql = "SELECT * FROM ;"
      ;
      $shippingFiles = $this->DB->select($sql);

      if (count($shippingFiles) > 0) {
        $rowCount = $shippingFiles[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $shippingFiles
            ])
        ;
      } else {
        $error = "Não temos arquivos de remessa gerados.";
      }
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [
          'module' => 'arquivos de remessa',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de arquivos "
        . "de remessa. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [
          'module' => 'arquivos de remessa',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de arquivos "
        . "de remessa. Erro interno."
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
   * Processa o arquivo de retorno com as ocorrências registradas pelo
   * banco.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function processReturnFile(
    Request $request,
    Response $response
  )
  {
    $this->debug(
      "Acesso ao processamento do arquivo de retorno."
    );

    // Recuperamos a informação do contratante
    $contractor = $this->authorization->getContractor();

    try {
      // Recupera o local de armazenamento dos arquivos de remessa
      $conciliationsPath = ''
        . $this->container['settings']['storage']['conciliations']
        . DIRECTORY_SEPARATOR . $contractor->id
        . DIRECTORY_SEPARATOR . $this->getYearAndMonth()
      ;

      // Verifica se o destino é um diretório válido
      if (is_dir($conciliationsPath)) {
        if (!is_writable($conciliationsPath)) {
          throw new RuntimeException(
            "O caminho de destino dos arquivos de conciliação bancária "
            . "não é gravável. " . $conciliationsPath
          );
        }
      } else {
        // Verifica se podemos criar o diretório corretamente
        if (false === @mkdir($conciliationsPath, 0777, true)) {
          // Limpamos o cache
          clearstatcache(true, $conciliationsPath);

          // Verificamos novamente se o diretório existe
          if (!is_dir($conciliationsPath)) {
            throw new RuntimeException(
              "Não é possível criar o caminho de destino dos arquivos "
              . "de conciliação bancária"
            );
          }
        }
      }
    } catch (RuntimeException $exception)
    {
      // Retornamos um erro como resposta
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(406)
        ->withJson([
            'result' => 'NOK',
            'params' => [],
            'message' => "Não foi possível processar o arquivo de "
              . "retorno. " . $exception->getMessage(),
            'data' => null
          ])
      ;
    }

    // Recupera o arquivo enviado
    $uploadedFiles = $request->getUploadedFiles();

    // Lida com uma entrada única com upload de arquivo único
    $cnabFile = $uploadedFiles['returnfile'];

    // Lida com o arquivo de retorno
    if ($this->fileHasBeenTransferred($cnabFile)) {
      try {
        // Verificamos se o arquivo já foi processado
        if ( file_exists(
               $conciliationsPath . DIRECTORY_SEPARATOR
               . basename($cnabFile->getClientFilename())
             ) ) {
          // Retornamos um erro como resposta
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404)
            ->withJson([
                'result' => 'NOK',
                'params' => [],
                'message' => "Este arquivo já foi processado",
                'data' => null
              ])
          ;
        }

        // Processamos o arquivo
        $filename = strtoupper(
          $cnabFile->getClientFilename()
        );
        $this->debug("Processando o arquivo " . $cnabFile->getClientFilename());
        $returnFile = ReturnFileFactory::make($cnabFile->file);
        $returnFile->process();

        // Monta o resultado do processamento

        // O conteúdo do resultado do arquivo
        $result = [
          'filename' => $filename,
          'content' => []
        ];

        // Os boletos registrados
        $registeredPayments = [];

        // Os boletos liquidados (pagos)
        $settledPayments = [];

        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Criamos um novo registro para informar o arquivo de retorno
        // sendo processado
        $transmissionFile = new BankingTransmissionFile();
        $transmissionFile->contractorid = $contractor->id;
        $transmissionFile->filename = 
          $this->getYearAndMonth() . DIRECTORY_SEPARATOR .
          $filename
        ;
        $transmissionFile->isshippingfile = false;
        $transmissionFile->save();
        $transmissionFileID = $transmissionFile->transmissionfileid;

        foreach ($returnFile AS $transaction) {
          $row = [
            'documentNumber' => $transaction->getDocumentNumber(),
            'dueDate' => $transaction->getDueDate('d/m/Y'),
            'valueToPay' => number_format($transaction->getDocumentValue(), 2, ',', '.'),
            'occurrenceDate' => $transaction->getOccurrenceDate('d/m/Y'),
            'occurrence' => ''
          ];

          // A flag indicativa de que o processamento está habilitado
          // para este registro. Quando falso, não modifica nada
          $processingEnabled = false;

          // A query que modifica os registros do pagamento
          $sql = null;

          // Inicialmente, indicamos que não temos erros
          $row['hasError'] = false;

          // Tentamos localizar a cobrança pelo número de
          // identificação do boleto no banco
          $ournumber = $transaction->getBankIdentificationNumber();
          $payment = BankingBilletPayment::join(
                'invoices', 'bankingbilletpayments.invoiceid', '=',
                'invoices.invoiceid'
              )
            ->join(
                'entities as customers', 'invoices.customerid', '=',
                'customers.entityid'
              )
            ->where(
                'bankingbilletpayments.ournumber', '=', $ournumber
              )
            ->get([
                'bankingbilletpayments.*',
                'customers.name AS customername'
              ])
          ;

          if ( $payment->isEmpty() ) {
            // Não conseguimos localizar, então tentamos pelo número
            // do documento, pois este é um boleto importado
            $documentNumber = $transaction->getDocumentNumber();
            if ( empty($documentNumber) ) {
              // Não será possível localizar este documento, então o
              // mesmo é ignorado
              $row['customername'] = "Não informado";
              $row['occurrence'] = '<span style="color: darkred;">Título não cadastrado.</span> ';
              $row['hasError'] = true;
            } else {
              // Separamos o número do documento de dígitos
              // verificadores
              preg_match("/(\d+)([-A-Za-z0-9]{1,2})?$/",
                $documentNumber , $matchs
              );
              $invoiceID = $matchs[1];
              $invoiceNumber = (count($matchs) > 2)
                ? $matchs[0]
                : null
              ;

              // Localizamos a cobrança pelo número do documento
              // apenas nos documentos importados
              $paymentQry = BankingBilletPayment::join(
                    'invoices', 'bankingbilletpayments.invoiceid',
                    '=', 'invoices.invoiceid'
                  )
                ->join(
                    'entities as customers', 'invoices.customerid',
                    '=', 'customers.entityid'
                  )
                ->where(
                    'bankingbilletpayments.invoiceid', '=', $invoiceID
                  )
              ;

              if ($invoiceNumber) {
                $paymentQry
                  ->orWhere(
                      'bankingbilletpayments.invoicenumber', '=',
                      $invoiceNumber
                    )
                ;
              }
              $payment = $paymentQry
                ->get([
                    'bankingbilletpayments.*',
                    'customers.name AS customername',
                  ])
              ;

              if ( $payment->isEmpty() ) {
                // Não localizou a cobrança
                $row['customername'] = "Não informado";
                $row['occurrence'] = '<span style="color: darkred;">Cobrança não localizada.</span> ';
                $row['hasError'] = true;
              } else {
                // Habilita atualização do título
                $payment = $payment->first();
                $processingEnabled = true;
                $paymentID = $payment->paymentid;
                $row['customername'] = $payment->customername;

                // Atualizamos também o nosso número para este título
                $secondarySQL = ""
                  . "UPDATE erp.bankingBilletPayments"
                  . "   SET ournumber = '{$ournumber}'"
                  . " WHERE paymentID = {$paymentID};"
                ;
                $this->DB->select($secondarySQL);
                unset($secondarySQL);
              }
            }
          } else {
            // Habilita atualização do título
            $payment = $payment->first();
            $processingEnabled = true;
            $paymentID = $payment->paymentid;
            $row['customername'] = $payment->customername;
          }

          // Conforme a ocorrência, executa as ações
          $row['paidValue'] = null;
          switch ($transaction->getOccurrenceType()) {
            case BilletOccurrence::LIQUIDATED:
              // Liquidação do título
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::LIQUIDATED
                  )
              ;
              $row['paidValue'] = number_format(
                $transaction->getPaidValue(), 2, ',', '.'
              );

              if ($processingEnabled) {
                // Verifica se o título está na condição de
                // "A Receber"
                if ($payment->paymentsituationid == PaymentSituation::RECEIVABLE) {
                  // Realizamos o pagamento do título
                  $newSituation = PaymentSituation::PAIDED;
                  $newDroppedType = BilletStatus::LIQUIDATED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET paymentSituationID = {$newSituation},"
                    . "       paidDate = '{$transaction->getOccurrenceDate('Y-m-d')}',"
                    . "       paidValue = " . number_format($transaction->getPaidValue(), 2, '.', '') . ","
                    . "       latePaymentInterest = " . number_format($transaction->getLatePaymentInterest(), 2, '.', '') . ","
                    . "       fineValue = " . number_format($transaction->getFineValue(), 2, '.', '') . ","
                    . "       creditDate = '{$transaction->getCreditDate('Y-m-d')}',"
                    . "       droppedTypeID = {$newDroppedType},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;

                  // Adiciona o pagamento à relação de pagamentos
                  // liquidados
                  $settledPayments[] = $paymentID;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::DROPPED:
              // Baixa do título
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::DROPPED
                  )
              ;

              $lapseOfTerm = false;
              foreach ($transaction->getReasons() AS $reason) {
                if ( stripos($reason, 'decurso de prazo') !== false ) {
                  $lapseOfTerm = true;
                }

                $row['occurrence'] .= ' por decurso de prazo';
                $row['paidValue'] = '';
              }

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Pago", "Negociado" ou "Renegociado"
                if (in_array(
                      $payment->paymentsituationid,
                      [
                        PaymentSituation::RECEIVABLE,
                        PaymentSituation::PAIDED,
                        PaymentSituation::NEGOTIATED,
                        PaymentSituation::RENEGOTIATED
                      ]
                    )) {
                  // Somente modificamos a situação se o título estava na
                  // situação "A Receber"
                  $newSituation = ($payment->paymentsituationid == PaymentSituation::RECEIVABLE)
                    ? (
                        ($lapseOfTerm)
                          ? PaymentSituation::RECEIVABLE
                          : PaymentSituation::CANCELED
                      )
                    : $payment->paymentsituationid
                  ;
                  $newDroppedType = ($lapseOfTerm)
                    ? BilletStatus::DROPPED_BECAUSE_LAPSE_OF_TERM
                    : BilletStatus::MANUALLY_DROPPED
                  ;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET paymentSituationID = {$newSituation},"
                    . "       droppedTypeID = {$newDroppedType},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::ENTRY:
              // Registro do título
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::ENTRY
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber" e
                // o boleto está não registrado
                if ( ($payment->paymentsituationid == PaymentSituation::RECEIVABLE) &&
                     ($payment->droppedtypeid == BilletStatus::NOT_REGISTERED) ) {
                  // Realizamos o registro do título
                  $newSituation = PaymentSituation::RECEIVABLE;
                  $newDroppedType = BilletStatus::REGISTERED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET paymentSituationID = {$newSituation},"
                    . "       droppedTypeID = {$newDroppedType},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;

                  // Adiciona o pagamento à relação de pagamentos registrados
                  $registeredPayments[] = $paymentID;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::CHANGE:
              // Modificado parâmetros do título
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::CHANGE
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Pago", "Negociado" ou "Renegociado"
                if (in_array(
                      $payment->paymentsituationid,
                      [
                        PaymentSituation::RECEIVABLE,
                        PaymentSituation::PAIDED,
                        PaymentSituation::NEGOTIATED,
                        PaymentSituation::RENEGOTIATED
                      ]
                    )) {
                  // Realizamos alguma modificação do título
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET dueDate = '{$transaction->getDueDate('Y-m-d')}',"
                    . "       valueToPay = " . number_format($transaction->getDocumentValue(), 2, '.', '') . ","
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::PROTESTED:
              // Entrada do título em cartório (Protestado)
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::PROTESTED
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está em aberto
                if ( (in_array(
                        $payment->paymentsituationid,
                        [
                          PaymentSituation::RECEIVABLE,
                          PaymentSituation::NEGOTIATED,
                          PaymentSituation::RENEGOTIATED
                        ]
                      )) &&
                     ($payment->droppedtypeid == BilletStatus::REGISTERED)
                   ) {
                  // Registramos o protesto do título
                  $restrictionToAdd = PaymentRestriction::PROTESTED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET restrictionID = restrictionID + {$restrictionToAdd},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::UNPROTESTED:
              // Retirado de cartório e manutenção em carteira
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::UNPROTESTED
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está protestado
                if ( (in_array(
                        $payment->paymentsituationid,
                        [
                          PaymentSituation::RECEIVABLE,
                          PaymentSituation::NEGOTIATED,
                          PaymentSituation::RENEGOTIATED
                        ]
                      )) &&
                     (PaymentRestriction::isProtested($payment->restrictionid))
                   ) {
                  // Realizamos a baixa do protesto do título
                  $restrictionToSubtract = PaymentRestriction::PROTESTED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET restrictionID = restrictionID - {$restrictionToSubtract},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::CREDIT_BLOCKED:
              // Confirmado recebimento pedido de negativação
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::CREDIT_BLOCKED
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está em aberto
                if ( (in_array(
                        $payment->paymentsituationid,
                        [
                          PaymentSituation::RECEIVABLE,
                          PaymentSituation::NEGOTIATED,
                          PaymentSituation::RENEGOTIATED
                        ]
                      )) &&
                     ($payment->droppedtypeid == BilletStatus::REGISTERED)
                   ) {
                  // Informamos a negativação do título
                  $restrictionToAdd = PaymentRestriction::CREDIT_BLOCKED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET restrictionID = restrictionID + {$restrictionToAdd},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::CREDIT_UNBLOCKED:
              // Confirmação pedido de exclusão de negativação (com ou sem
              // baixa)
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::CREDIT_UNBLOCKED
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está negativado
                if ( (in_array(
                        $payment->paymentsituationid,
                        [
                          PaymentSituation::RECEIVABLE,
                          PaymentSituation::NEGOTIATED,
                          PaymentSituation::RENEGOTIATED
                        ]
                      )) &&
                     (PaymentRestriction::isCreditBlocked($payment->restrictionid))
                   ) {
                  // Realizamos a negtivação do título
                  $restrictionToSubtract = PaymentRestriction::CREDIT_BLOCKED;
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET restrictionID = restrictionID - {$restrictionToSubtract},"
                    . "       hasError = false,"
                    . "       reasonForError = null"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::OTHERS:
              // Outros motivos
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::OTHERS
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              $row['occurrence'] .= ', '
                . implode(
                    "\n",
                    array_filter($transaction->getReasons())
                  )
              ;

              break;
            case BilletOccurrence::TARIFF:
              // Débito de tarifas/custas
              $row['occurrence'] .= $transaction->getOccurrenceDescription();

              break;
            case BilletOccurrence::ABATEMENT:
              // Abatimento do valor
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::ABATEMENT
                  )
                . '. ' . $transaction->getOccurrenceDescription()
                . '. Valor do abatimento de '
                . number_format(
                    $transaction->getAbatementValue(),
                    2, ',', '.'
                  )
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está em aberto
                // ou o boleto já foi pago, pois pode ocorrer que a
                // instrução de desconto/abatimento venha depois da
                // instrução do respectivo pagamento
                if (
                     (
                       (in_array(
                          $payment->paymentsituationid,
                          [
                            PaymentSituation::RECEIVABLE,
                            PaymentSituation::NEGOTIATED,
                            PaymentSituation::RENEGOTIATED
                          ]
                        )) &&
                       ($payment->droppedtypeid == BilletStatus::REGISTERED)
                     )  ||
                     (
                       ($payment->paymentsituationid == PaymentSituation::PAIDED) &&
                       ($payment->droppedtypeid == BilletStatus::LIQUIDATED)
                     )
                   ) {
                  // Realizamos abatimento do valor do título
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET abatementValue = " . number_format($transaction->getAbatementValue(), 2, '.', '')
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            case BilletOccurrence::UNABATEMENT:
              // Abatimento do valor
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::UNABATEMENT
                  )
                . '. ' . $transaction->getOccurrenceDescription()
              ;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber",
                // "Negociado" ou "Renegociado" e o boleto está em aberto
                // ou o boleto já foi pago, pois pode ocorrer que a
                // instrução de retirada do desconto/abatimento venha
                // depois da instrução do respectivo pagamento
                if (
                     (
                       (in_array(
                          $payment->paymentsituationid,
                          [
                            PaymentSituation::RECEIVABLE,
                            PaymentSituation::NEGOTIATED,
                            PaymentSituation::RENEGOTIATED
                          ]
                        )) &&
                       ($payment->droppedtypeid == BilletStatus::REGISTERED)
                     )  ||
                     (
                       ($payment->paymentsituationid == PaymentSituation::PAIDED) &&
                       ($payment->droppedtypeid == BilletStatus::LIQUIDATED)
                     )
                   ) {
                  // Realizamos a retirada do abatimento do valor do título
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET abatementValue = 0.00"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }

              break;
            default:
              // Ocorrência de erro
              $row['occurrence'] .= ''
                . BilletOccurrence::toString(
                    (int) BilletOccurrence::ERROR
                  )
                . '. ' . $transaction->getOccurrenceDescription()
                . ', '
                . implode(
                    "\n",
                    array_filter($transaction->getReasons())
                  )
              ;
              $row['hasError'] = true;

              if ($processingEnabled) {
                // Verifica se o título está na condição de "A Receber"
                if ($payment->paymentsituationid == PaymentSituation::RECEIVABLE) {
                  // Realizamos o registro de erro no título, sem alterar
                  // a situação do boleto
                  $sql = ""
                    . "UPDATE erp.bankingBilletPayments"
                    . "   SET hasError = true,"
                    . "       reasonForError = '" . implode(', ', $transaction->getReasons()) . "'"
                    . " WHERE paymentID = {$paymentID}"
                    . "   AND contractorid = " . $contractor->id . ";"
                  ;
                } else {
                  $row['occurrence'] .= '. Ignorando alteração';
                }
              }
          }

          if ($processingEnabled) {
            if ($sql) {
              // Executamos a atualização do registro
              $this->DB->select($sql);
            }

            // Sempre registramos a informação do movimento ocorrido
            $secondarySQL = ""
              . "INSERT INTO erp.bankingBilletOccurrences"
              . "       (contractorID, paymentID, occurrenceTypeID, "
              . "        occurrenceCode, description, reasons, "
              . "        occurrenceDate, tariffValue, returnFileID) VALUES"
              . "       ({$contractor->id}, {$paymentID}, "
              . "        {$transaction->getOccurrenceType()}, "
              . "        {$transaction->getOccurrenceCode()}, "
              . "        '{$transaction->getOccurrenceDescription()}', "
              . "        '" . implode(', ', $transaction->getReasons()) . "', "
              . "        '{$transaction->getOccurrenceDate('Y-m-d')}', "
              . "        " . number_format($transaction->getTariffValue(), 2, '.', '') . ","
              . "        {$transmissionFileID});"
            ;
            $this->DB->select($secondarySQL);
            unset($secondarySQL);
          }

          // Acrescenta as informações do registro processado
          $result['content'][] = $row;
        }

        // Agenda o envio dos e-mails com os comprovantes à todos àqueles
        // cujos pagamentos foram liquidados
        foreach ($settledPayments AS $paymentID) {
          // Insere o pagamento na fila para envio de recibo
          $sql = ""
            . "INSERT INTO erp.emailsQueue"
            . "       (contractorID, mailEventID, originRecordID, recordsOnScope) VALUES"
            . "       ({$contractor->id}, 4, {$paymentID}, '{{$paymentID}}');"
          ;
          $this->DB->select($sql);
        }

        // Agenda o envio dos e-mails com os boletos à todos àqueles cujos
        // pagamentos foram registrados
        foreach ($registeredPayments AS $paymentID) {
          // Insere o pagamento na fila para envio de boleto
          $sql = ""
            . "INSERT INTO erp.emailsQueue"
            . "       (contractorID, mailEventID, originRecordID, recordsOnScope) VALUES"
            . "       ({$contractor->id}, 1, {$paymentID}, erp.getPaymentScope({$paymentID}));"
          ;
          $this->DB->select($sql);
        }

        // Move o arquivo para o local, mantendo o seu nome
        $cnabFile->moveTo(
          $conciliationsPath . DIRECTORY_SEPARATOR . $filename
        );
        
        // Efetiva a transação
        $this->DB->commit();

        $this->info(
          "Processado arquivo de retorno {name}",
          [ 'name' => $filename ]
        );

        // Retorna o resultado do processamento do arquivo de retorno
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => [],
              'message' => "Arquivo {$filename} processado com "
                . "sucesso",
              'data' => $result
            ])
        ;
      }
      catch (RuntimeException $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o evento
        $this->error(
          "Não foi possível processar o arquivo de retorno {name}. "
          . "{error}",
          [
            'name' => $filename,
            'error' => $exception->getMessage()
          ]
        );

        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(406)
          ->withJson([
              'result' => 'NOK',
              'params' => [],
              'message' => "Não foi possível processar o arquivo de "
                . "retorno. " . $exception->getMessage(),
              'data' => null
            ])
        ;
      }
    } else {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(406)
        ->withJson([
            'result' => 'NOK',
            'params' => [],
            'message' => "Não foi possível processar o arquivo de "
              . "retorno. Arquivo não carregado.",
            'data' => null
          ])
      ;
    }
  }
}
