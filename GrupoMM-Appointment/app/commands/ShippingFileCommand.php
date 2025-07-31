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
 * O controlador do comando de geração do arquivo de remessa.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace App\Commands;

use App\Models\BankingBilletPayment;
use App\Models\BankingTransmissionFile;
use App\Models\DefinedMethod;
use App\Models\Entity AS Contractor;
use Carbon\Carbon;
use Core\Console\Command;
use Core\Controllers\QueryTrait;
use Core\Payments\AgentEntity;
use Core\Payments\BankingBillet\BankingBilletFactory;
use Core\Payments\Cnab\Shipping\Cnab400\Bradesco AS ShippingFile;
use Exception;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final class ShippingFileCommand
  extends Command
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

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
   * Exibe uma mensagem com a explicação do funcionamento do comando.
   * 
   * @return void
   */
  private function helpCommand()
  {
    $this->console->out("<white>O comando de geração do arquivo de "
      . "remessa nos permite enviar ao banco todos os boletos emitidos "
      . "para registro. O formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>shippingfile"
    );
    $this->console->out();
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


  /**
   * O comando a ser executado
   *
   * @param string[] $args
   *   Os argumentos da linha de comando
   *
   * @return void
   */
  public function command(array $args)
  {
    // Registra a execução
    $this->debug("Executando o comando de geração de arquivo de "
      . "remessa em modo console.",
      [ ]);

    try {
      // Recuperamos a informação do contratante
      $contractor = $this->getContractor($args);

      // Recuperamos os dados do emissor
      $emitter = $this->getEmitter($contractor->id);

      // Recuperamos as configurações de boletos existentes
      // TODO: Aqui precisamos pegar a informação da nova tabela
      // erp.billetDispatching
      $definedBillets = $this->getDefinedBankingBillets($contractor->id);

      // Vamos iniciar um loop, onde cada boleto é adicionado ao arquivo
      // de remessa para envio para registro
      foreach ($definedBillets AS $definedBillet) {
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
          // Os dados da conta bancária
          ->setAgencyNumber($definedBillet->agencynumber)
          ->setAccountNumber($definedBillet->accountnumber)
        ;

        // Obtém as configurações do boleto
        $parameters = json_decode($definedBillet->parameters);

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

        // Iniciamos nossa consulta para obter as informações do(s)
        // pagamento(s) que estejam definidos para este boleto
        // configurado
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
          ->where('bankingbilletpayments.contractorid', '=', $contractor->id)
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
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          $this->console->out("<lightYellow><bgRed><bold>Atenção!");
          $this->console->out();
          $this->console->out("Não temos nenhuma cobrança a ser "
            . "enviada por remessa"
          );
          $this->console->out();
          
          return;
        }

        // Percorremos os pagamentos para gerar os boletos
        //$billets = []; ???
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

        $shippingFile = (new ShippingFile())
          ->setWallet($payment->wallet)
          ->setEmitter($emitterAgent)
          ->setEmitterCode("{$parameters->emitterCode}")
          ->setSequentialShippingNumber($shippingCounter)
          // Repetimos a quantidade de vezes necessárias e/ou usamos uma
          // matriz que contenha todos os boletos e adicionamos de uma vez
          ->addBillets($billets)
        ;

        // Recupera o local de armazenamento dos boletos
        $targetPath = $this->container['settings']['storage']['conciliations']
          . DIRECTORY_SEPARATOR . $contractor->id
          . DIRECTORY_SEPARATOR . $this->getYearAndMonth()
        ;

        // Verifica se o destino é um diretório válido
        if (is_dir($targetPath)) {
          if (!is_writable($targetPath)) {
            throw new InvalidArgumentException("O caminho de destino dos "
              . "uploads não é gravável"
            );
          }
        } else {
          // Verifica se podemos criar o diretório corretamente
          if (false === @mkdir($targetPath, 0777, true)) {
            // Limpamos o cache
            clearstatcache(true, $targetPath);

            // Verificamos novamente se o diretório existe
            if (!is_dir($targetPath)) {
              throw new InvalidArgumentException("Não é possível criar o "
                . "caminho de destino dos uploads"
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

        $this->console->out("Gerado arquivo de remessa ",
          $filename
        );
      }
    }
    catch (RuntimeException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o evento
      $this->error("Não foi possível recuperar os dados do emissor");

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível recuperar os dados do "
        . "emissor. " . $exception->getMessage()
      );
      $this->console->out();

      return;
    }
  }


  // ===================================================[ Auxiliares ]==

  /**
   * Recupera o tempo decorrido (em segundos) de uma forma legível ao
   * usuário.
   * 
   * @param int $seconds
   *   O tempo em segundos
   * 
   * @return string
   *   O tempo de maneira legível
   */
  protected function getHumanTime(int $seconds): string
  {
    $humanTime = '0';

    if ($seconds > 0) {
      // Convertemos o tempo (em segundos) em um valor
      // legível ao usuário, ignorando segundos neste cálculo
      $now      = Carbon::now()->locale('pt_BR');
      $interval = Carbon::now()->locale('pt_BR')
        ->addSeconds($seconds)
      ;
      $humanTime = $now
        ->longAbsoluteDiffForHumans($interval, 4)
      ;
    }

    return $humanTime;
  }
  
  /**
   * Recupera os dados do contratante.
   * 
   * @param array $args
   *   Os argumentos de linha de comando
   * 
   * @return mixed
   *   Os dados do contratante
   */
  protected function getContractor(array $args) {
    // Verifica se foram fornecidos os argumentos na linha de comando
    $UUID = null;

    if (count($args) > 0) {
      // Verifica se foi informado o UUID do contratante
      $uuidParm = array_filter($args, function ($arg) {
        return (strpos($arg, "uuid") === 0);
      });
      if (count($uuidParm) > 0) {
        $UUID = strtolower(substr(array_slice($uuidParm, 0, 1)[0], 5));

        if (strlen($UUID) !== 36) {
          // Devemos interromper, pois a UUID não é válida
          throw new RuntimeException("A UUID '{$UUID}' informada é "
            . "inválida")
          ;
        }
      }
    }
    
    if (is_null($UUID)) {
      // Recupera da configuração o UUID do contratante base
      $settings = $this->container['settings']['contractor'];
      $UUID = $settings['uuid'];
    }

    // Recupera a informação do contratante
    $contractor = Contractor::where("contractor", "true")
      ->where("entityuuid", "=", $UUID)
      ->get([
          "entityid AS id",
          "name",
          "stckey"
        ])
      ->first();
    ;

    if (!$contractor) {
      // Devemos interromper, pois não encontramos um contratante à
      // partir da UUID informada
      throw new RuntimeException("Não temos um contratante com a UUID '{$UUID}'");
    }

    return $contractor;
  }

  protected function showApplicationData()
  {
    $this->console->out("  _____            _           ___ ___ ___  ");
    $this->console->out(" |_   _| _ __ _ __| |_____ _ _| __| _ \ _ \ ");
    $this->console->out("   | || '_/ _` / _| / / -_) '_| _||   /  _/ ");
    $this->console->out("   |_||_| \__,_\__|_\_\___|_| |___|_|_\_|   ");
    $this->console->out("                                            ");
  }
}