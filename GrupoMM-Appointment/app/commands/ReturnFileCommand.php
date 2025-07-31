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
 * O controlador do comando de processamento do arquivo de retorno.
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
use Core\Helpers\Path;
use Core\Payments\Cnab\BilletOccurrence;
use Core\Payments\Cnab\BilletStatus;
use Core\Payments\Cnab\Returning\ReturnFileFactory;
use Core\Payments\PaymentRestriction;
use Core\Payments\PaymentSituation;
use Exception;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final class ReturnFileCommand
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
    $this->console->out("<white>O comando de processamento do arquivo "
      . "de retorno nos permite receber do banco todos os boletos "
      . "registrados, bem como àqueles que foram pagos, baixados e/ou "
      . "enviados para protesto. O formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>returnfile <lightCyan>file=<filename>",
      " <lightMagenta>[<argumentos>]"
    );
    $this->console->out();
    $this->console->out("<white>Onde: ");
    $this->console->out("<lightYellow> - filename: <white>É o nome do ",
      "arquivo de retorno a ser processado"
    );
    $this->console->out("<lightYellow> - argumentos: <white>um ou mais "
      . "argumentos opcionais a serem passados ao processamento"
    );
    $this->console->out("<lightMagenta>   - onlytest: <white>habilita o "
      . "modo de teste e depuração"
    );
    $this->console->out("<lightMagenta>   - reprocess: <white>habilita o "
      . "modo de reprocesssamento"
    );
    $this->console->out();
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

    // Recuperamos a informação do contratante
    $contractor = $this->getContractor($args);

    // Recupera o local de armazenamento dos arquivos de retorno
    $targetPath = $this->container['settings']['storage']['conciliations']
      . DIRECTORY_SEPARATOR . $contractor->id
      . DIRECTORY_SEPARATOR . $this->getYearAndMonth()
    ;

    if (count($args) > 0) {
      // Verifica se foi informado o arquivo
      $filenameParm = array_filter($args, function ($arg) {
        return (strpos($arg, "file") === 0);
      });

      if (count($filenameParm) > 0) {
        $filename = substr(array_slice($filenameParm, 0, 1)[0], 5);
      } else {
        // Registra o erro
        $this->error("Não foi fornecido o arquivo de retorno para ser "
          . "processado.",
          [ ]);

        // Como não foi fornecido o nome de um provedor para os serviços
        // do sistema de integração que se deseja sincronizar, então exibe
        // uma mensagem de ajuda
        $this->helpCommand();

        return;
      }

      // Verifica se foi informado o modo de testes
      $onlytest = array_filter($args, function ($arg) {
        return (strpos($arg, "onlytest") === 0);
      });
      $debugMode = false;
      if (count($onlytest) > 0) {
        $debugMode = true;
      }

      // Verifica se foi informado o modo de reprocessamento
      $reprocess = array_filter($args, function ($arg) {
        return (strpos($arg, "reprocess") === 0);
      });
      $reworksMode = false;
      if (count($reprocess) > 0) {
        $reworksMode = true;
      }
    } else {
      // Registra o erro
      $this->error("Não foi fornecido o arquivo de retorno para ser "
        . "processado.",
        [ ]);

      // Como não foi fornecido o nome de um provedor para os serviços
      // do sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->helpCommand();

      return;
    }

    // Vamos iniciar um loop, onde cada boleto é adicionado ao arquivo
    // de remessa para envio para registro
    try {

      // Verifica se o arquivo existe
      $cnabFile = new Path($filename);
      if (!$cnabFile->exists() || !$cnabFile->isFile()) {
        throw new RuntimeException("O arquivo {$filename} não existe");
      }

      // Verifica se o destino é um diretório válido
      if (is_dir($targetPath)) {
        if (!is_writable($targetPath)) {
          throw new InvalidArgumentException("O caminho de destino dos "
            . "arquivos de retorno não é gravável"
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
              . "caminho de destino dos arquivos de retorno"
            );
          }
        }
      }

      if (($debugMode === false) && ($reworksMode === false)) {
        // Verificamos se o arquivo já foi processado
        if ( file_exists(
               $targetPath . DIRECTORY_SEPARATOR . $cnabFile->getBasename()
             )) {
          // Exibe uma mensagem de informação
          $this->console->out("<lightYellow><bgRed><bold>Atenção!");
          $this->console->out();
          $this->console->out("Este arquivo já foi processado");
          $this->console->out();

          return;
        }
      }

      // Processamos o arquivo
      $returnFile = ReturnFileFactory::make(strtoupper($filename));
      $returnFile->process();

      $this->console->out("<white>Processando arquivo "
        . "<lightWhite>{$filename}:"
      );

      if ($debugMode) {
        // Despejamos o conteúdo do arquivo na tela e retornamos
        foreach ($returnFile AS $transaction) {
          // Exibimos as informações do que estamos processando
          $this->console->out("<white> Nº de identificação banco: "
            . "<lightWhite>" . $transaction->getBankIdentificationNumber()
          );
          $this->console->out("<white>  Nº do documento: "
            . "<lightWhite>" . $transaction->getDocumentNumber()
          );
          $this->console->out("<white>   Nº do controle: "
            . "<lightWhite>" . $transaction->getControlNumber()
          );
          $this->console->out("<white>  Data vencimento: "
            . "<lightYellow>" . $transaction->getDueDate('d/m/Y')
          );
          $this->console->out("<white>  Valor documento: "
            . "<lightYellow>R$ "
            . number_format($transaction->getDocumentValue(), 2, ',', '.')
          );
          $this->console->out("<white> Valor abatimento: "
            . "<lightYellow>R$ "
            . number_format($transaction->getAbatementValue(), 2, ',', '.')
          );
          $this->console->out("<white>     Valor tarifa: "
            . "<lightYellow>R$ "
            . number_format($transaction->getTariffValue(), 2, ',', '.')
          );
          $this->console->out("<white>        Valor IOF: "
            . "<lightYellow>R$ "
            . number_format($transaction->getIOFValue(), 2, ',', '.')
          );
          $this->console->out("<white>            Multa: "
            . "<lightYellow>R$ "
            . number_format($transaction->getLatePaymentInterest(), 2, ',', '.')
          );
          $this->console->out("<white>    Juros de mora: "
            . "<lightYellow>R$ "
            . number_format($transaction->getFineValue(), 2, ',', '.')
          );
          $this->console->out();
          $this->console->out("<white> Código ocorrência: "
            . "<lightCyan>" . $transaction->getOccurrenceCode()
            . " - " . $transaction->getOccurrenceDescription()
          );
          $this->console->out("<white>   Data ocorrência: "
            . "<lightCyan>" . $transaction->getOccurrenceDate('d/m/Y')
          );
          foreach ($transaction->getReasons() AS $reason) {
            $this->console->out(" * <lightRed>" . $reason);
          }
          $this->console->out("<white>     Ação aplicada: "
            . "<lightYellow>" . $transaction->getOccurrenceType()
            . " - "
            . BilletOccurrence::toString( $transaction->getOccurrenceType() )
          );
          $this->console->out();
          $this->console->out("----------------------------------------");
          $this->console->out();
        }

        return;
      }

      // Iniciamos a transação
      $this->DB->beginTransaction();

      if ($reworksMode) {
        // Obtemos o ID do registro do arquivo de retorno
        $baseName = $cnabFile->getBasename();
        $transmissionFile = BankingTransmissionFile::where('contractorid',
            '=', $contractor->id)
          ->where('isshippingfile', false)
          ->whereRaw("filename ILIKE '%%{$baseName}'")
          ->get([
              'transmissionfileid'
            ])
          ->first()
        ;
        $transmissionFileID = $transmissionFile->transmissionfileid;
      } else {
        // Criamos um novo registro do arquivo de retorno
        $transmissionFile = new BankingTransmissionFile();
        $transmissionFile->contractorid = $contractor->id;
        $transmissionFile->filename = 
          $this->getYearAndMonth() . DIRECTORY_SEPARATOR .
          $cnabFile->getBasename()
        ;
        $transmissionFile->isshippingfile = false;
        $transmissionFile->save();
        $transmissionFileID = $transmissionFile->transmissionfileid;
      }

      // Os boletos registrados
      $registeredPayments = [];
      // Os boletos liquidados (pagos)
      $settledPayments = [];

      foreach ($returnFile AS $transaction) {
        // Exibimos as informações do que estamos processando
        $this->console->out("<white> Nº do documento: "
          . "<lightWhite>" . $transaction->getDocumentNumber()
        );
        $this->console->out("<white>  Nº do controle: "
          . "<lightWhite>" . $transaction->getControlNumber()
        );
        $this->console->out("<white> Data vencimento: "
          . "<lightYellow>" . $transaction->getDueDate('d/m/Y')
        );
        $this->console->out("<white> Valor documento: "
          . "<lightYellow>R$ "
          . number_format($transaction->getDocumentValue(), 2, ',', '.')
        );
        $this->console->out();
        $this->console->out("<white> Data ocorrência: "
          . "<lightCyan>" . $transaction->getOccurrenceDate('d/m/Y')
        );

        // A flag indicativa de que o processamento está habilitado para
        // este registro. Quando falso, não modifica nada
        $processingEnabled = false;

        // A query que modifica os registros do pagamento
        $sql = null;

        // Tentamos localizar a cobrança pelo número de identificação do
        // boleto no banco
        $ournumber = $transaction->getBankIdentificationNumber();
        $payment = BankingBilletPayment::where('ournumber', '=',
              $ournumber
            )
          ->get()
        ;

        if ( $payment->isEmpty() ) {
          // Não conseguimos localizar, então tentamos pelo número do
          // documento, pois este é um boleto importado
          $documentNumber = $transaction->getDocumentNumber();
          if ( empty($documentNumber) ) {
            // Não será possível localizar este documento, então o mesmo
            // é ignorado
            $this->console->out("<lightRed>Ignorando movimento para "
              . "título não cadastrado"
            );
          } else {
            // Separamos o número do documento de dígitos verificadores
            preg_match("/(\d+)([-A-Za-z0-9]{1,2})?$/",
              $documentNumber , $matchs
            );
            $invoiceID = $matchs[1];
            $invoiceNumber = (count($matchs) > 2)
              ? $matchs[0]
              : null
            ;

            // Localizamos a cobrança pelo número do documento apenas
            // nos documentos importados
            $paymentQry = BankingBilletPayment::where('invoiceid', '=',
                  $invoiceID
                )
            ;

            if ($invoiceNumber) {
              $paymentQry
                ->orWhere('invoicenumber', '=', $invoiceNumber)
              ;
            }
            $payment = $paymentQry
              ->get()
            ;

            if ( $payment->isEmpty() ) {
              // Não localizou a cobrança
              $this->console->out("<lightRed>Cobrança não localizada");
            } else {
              // Habilita atualização do título
              $payment = $payment->first();
              $processingEnabled = true;
              $paymentID = $payment->paymentid;

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
        }

        switch ($transaction->getOccurrenceType()) {
          case BilletOccurrence::LIQUIDATED:
            // Liquidação do título
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Título liquidado"
            );
            $this->console->out("<white>      Valor pago: "
              . "<lightGreen>R$ "
              . number_format($transaction->getPaidValue(), 2, ',', '.')
            );
            $this->console->out("<white>     Valor multa: "
              . "<lightRed>R$ "
              . number_format($transaction->getFineValue(), 2, ',', '.')
            );
            $this->console->out("<white>   Juros de mora: "
              . "<lightRed>R$ "
              . number_format($transaction->getLatePaymentInterest(), 2, ',', '.')
            );
            $this->console->out("<white>    Creditado em: "
              . "<lightCyan>" . $transaction->getCreditDate('d/m/Y')
            );

            if ($processingEnabled) {
              // Verifica se o título está na condição de "A Receber"
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

                // Adiciona o pagamento à relação de pagamentos liquidados
                $settledPayments[] = $paymentID;
              } else {
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::DROPPED:
            // Baixa do título
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Baixado"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );

            $lapseOfTerm = false;
            foreach ($transaction->getReasons() AS $reason) {
              if ( stripos($reason, 'decurso de prazo') !== false ) {
                $lapseOfTerm = true;
              }

              $this->console->out(" * <lightRed>" . $reason);
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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::ENTRY:
            // Registro do título
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Registrado"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            $this->console->out("<white>    Valor tarifa: "
              . "<lightRed>R$ "
              . number_format($transaction->getTariffValue(), 2, ',', '.')
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::CHANGE:
            // Modificado parâmetros do título
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Modificado"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::PROTESTED:
            // Entrada do título em cartório (Protestado)
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Protestado"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::UNPROTESTED:
            // Retirado de cartório e manutenção em carteira
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Retirado protesto e manutenção em carteira"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::CREDIT_BLOCKED:
            // Confirmado recebimento pedido de negativação
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Negativado"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::CREDIT_UNBLOCKED:
            // Confirmação pedido de exclusão de negativação (com ou sem
            // baixa)
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Retirada negativação"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::OTHERS:
            // Outros motivos
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Outros motivos"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

            break;
          case BilletOccurrence::TARIFF:
            // Débito de tarifas/custas
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>" . $transaction->getOccurrenceDescription()
            );
            $this->console->out("<white>    Valor tarifa: "
              . "<lightRed>R$ "
              . number_format($transaction->getTariffValue(), 2, ',', '.')
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

            break;
          case BilletOccurrence::ABATEMENT:
            // Abatimento do valor
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Abatimento do valor"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            $this->console->out("<white>Valor abatimento: "
              . "<lightYellow>R$ "
              . number_format($transaction->getAbatementValue(), 2, ',', '.')
            );

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          case BilletOccurrence::UNABATEMENT:
            // Abatimento do valor
            $this->console->out("<white>      Ocorrência: "
              . "<lightCyan>Retirada de abatimento do valor"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightYellow>" . $transaction->getOccurrenceDescription()
            );
            $this->console->out("<white>Valor abatimento: "
              . "<lightYellow>R$ "
              . number_format($transaction->getAbatementValue(), 2, ',', '.')
            );

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }

            break;
          default:
            // BilletOccurrence::ERROR
            // Ocorrência de erro
            $this->console->out("<white>      Ocorrência: "
              . "<lightRed>Erro"
            );
            $this->console->out("<white>          Motivo: "
              . "<lightRed>" . $transaction->getOccurrenceDescription()
            );
            foreach ($transaction->getReasons() AS $reason) {
              $this->console->out(" * <lightRed>" . $reason);
            }

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
                $this->console->out("<lightRed>Ignorando alteração");
              }
            }
        }

        $storeOccurrence = true;
        if ($processingEnabled) {
          if ($sql) {
            // Executamos a atualização do registro
            $this->DB->select($sql);
          }

          if ($reprocess) {
            // Primeiramente, precisamos verificar se o registro desta
            // ocorrência ainda não foi armazenado
            $secondarySQL = ""
              . "SELECT count(*) "
              . "  FROM erp.bankingBilletOccurrences"
              . " WHERE contractorID = {$contractor->id}"
              . "   AND paymentID = $paymentID"
              . "   AND occurrenceTypeID = {$transaction->getOccurrenceType()}"
              . "   AND occurrenceCode = {$transaction->getOccurrenceCode()}"
              . "   AND occurrenceDate = '{$transaction->getOccurrenceDate('Y-m-d')}'"
            ;
            $result = $this->DB->select($secondarySQL);
            if ($result[0]->count > 0) {
              $storeOccurrence = false;
            }
          }

          if ($storeOccurrence) {
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
        }

        echo "\n------------------------------------------------------------\n";
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

      if (! $reprocess) {
        // Copia o arquivo para o local, mantendo o seu nome
        copy(
          $filename,
          $targetPath . DIRECTORY_SEPARATOR . $cnabFile->getBasename()
        );
      }
      
      // Efetiva a transação
      $this->DB->commit();

      $this->console->out("Processado arquivo de retorno ",
        $filename
      );
    }
    catch (RuntimeException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o evento
      $this->error("Não foi possível recuperar os dados do emissor");

      // Exibe uma mensagem de ajuda
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