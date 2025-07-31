<?php
/*
 * This file is part of the payment's API library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Uma transação do arquivo de retorno.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning\Cnab400;

use Carbon\Carbon;
use Core\Payments\Cnab\BilletOccurrence;
use Core\Payments\Cnab\MagicTrait;
use Core\Payments\Cnab\Returning\TransactionInterface;

class Transaction
  implements TransactionInterface
{
  /**
   * Importa as características para criação dos métodos mágicos.
   */
  use MagicTrait;

  /**
   * A carteira.
   * 
   * @var string
   */
  protected $wallet;

  /**
   * Número de identificação do título no banco.
   * 
   * @var string
   */
  protected $bankIdentificationNumber;

  /**
   * Número do documento. É usado para informar o número do documento
   * (a identificação do número da fatura, duplicata, etc) cujo valor
   * está sendo cobrado nesta transação.
   * 
   * @var string
   */
  protected $documentNumber;

  /**
   * Número de controle interno (da aplicação) para controle da remessa.
   * 
   * @var string
   */
  protected $controlNumber;

  /**
   * O código de liquidação do título.
   * 
   * @var int
   */
  protected $settlementCode;

  /**
   * O código da ocorrência.
   * 
   * @var int
   */
  protected $occurrenceCode;

  /**
   * O código do tipo de ocorrência.
   * 
   * @var int
   */
  protected $occurrenceType;

  /**
   * A descrição da ocorrência.
   * 
   * @var string
   */
  protected $occurrenceDescription;

  /**
   * O motivo da rejeição do registro do título.
   * 
   * @var int
   */
  protected $rejectionReason;

  /**
   * A data da ocorrência.
   * 
   * @var Carbon
   */
  protected $occurrenceDate;

  /**
   * A data de vencimento do título.
   * 
   * @var Carbon
   */
  protected $dueDate;

  /**
   * A data da ocorrência do crédito na conta.
   * 
   * @var Carbon
   */
  protected $creditDate;

  /**
   * O valor do título.
   * 
   * @var float
   */
  protected $documentValue;

  /**
   * O valor da tarifa cobrada.
   * 
   * @var float
   */
  protected $tariffValue;

  /**
   * O valor cobrado a título de outras despesas.
   * 
   * @var float
   */
  protected $otherExpensesValue;

  /**
   * O valor do IOF incidido sobre a transação.
   * 
   * @var float
   */
  protected $iofValue;

  /**
   * O valor de abatimento aplicado.
   * 
   * @var float
   */
  protected $abatementValue;

  /**
   * O valor de desconto aplicado.
   * 
   * @var float
   */
  protected $discountValue;

  /**
   * O valor efetivamente pago.
   * 
   * @var float
   */
  protected $paidValue;

  /**
   * O valor cobrado à título de juros de mora sobre o valor do título.
   * 
   * @var float
   */
  protected $latePaymentInterest;

  /**
   * O valor cobrado à título de multa sobre o valor do título.
   * 
   * @var float
   */
  protected $fineValue;

  /**
   * Os motivos da ocorrência.
   * 
   * @var array
   */
  protected $reasons;

  /**
   * A URL para o SPI do sistema PIX.
   * 
   * @var string
   */
  protected $spiUrl;

  /**
   * O ID da transação no sistema PIX.
   * 
   * @var string
   */
  protected $txId;

  /**
   * Obtém a carteira.
   * 
   * @return string
   */
  public function getWallet(): string
  {
    return $this->wallet;
  }

  /**
   * Define a carteira.
   * 
   * @param string $wallet
   *   A carteira
   *
   * @return $this
   *   A instância da transação
   */
  public function setWallet(string $wallet): self
  {
    $this->wallet = $wallet;

    return $this;
  }

  /**
   * Obtém o número de identificação do título no banco ("nosso número"),
   * baseado nas regras da instituição pela qual o boleto foi emitido.
   * 
   * @return string
   */
  public function getBankIdentificationNumber(): string
  {
    return $this->bankIdentificationNumber;
  }

  /**
   * Define o número de identificação do título no banco ("nosso
   * número"), baseado nas regras da instituição pela qual o boleto foi
   * emitido.
   * 
   * @param string $bankIdentificationNumber
   *   O número de identificação do título no banco
   *
   * @return $this
   *   A instância da transação
   */
  public function setBankIdentificationNumber(
    string $bankIdentificationNumber
  ): self
  {
    $this->bankIdentificationNumber = $bankIdentificationNumber;

    return $this;
  }

  /**
   * Obtém o número do documento.
   * 
   * @return string
   */
  public function getDocumentNumber(): string
  {
    return $this->documentNumber;
  }

  /**
   * Define o número do documento.
   * 
   * @param string $documentNumber
   *   O número do documento
   *
   * @return $this
   *   A instância da transação
   */
  public function setDocumentNumber(string $documentNumber): self
  {
    $this->documentNumber = ltrim(trim($documentNumber, ' '), '0');

    return $this;
  }

  /**
   * Obtém o número de controle interno (da aplicação) para controle da
   * remessa.
   * 
   * @return string
   */
  public function getControlNumber(): string
  {
    return $this->controlNumber;
  }

  /**
   * Define o número de controle interno (da aplicação) para controle da
   * remessa.
   * 
   * @param string $controlNumber
   *   O número de controle interno
   *
   * @return $this
   *   A instância da transação
   */
  public function setControlNumber(string $controlNumber): self
  {
    $this->controlNumber = $controlNumber;

    return $this;
  }

  /**
   * Obtém o código de liquidação do título.
   *
   * @return int
   */
  public function getSettlementCode(): int
  {
    return $this->settlementCode ?: 0;
  }

  /**
   * Define o código de liquidação do título.
   *
   * @param int $settlementCode
   *   O código de liquidação do título
   *
   * @return $this
   *   A instância da transação
   */
  public function setSettlementCode(int $settlementCode): self
  {
    $this->settlementCode = $settlementCode;

    return $this;
  }

  /**
   * Obtém se a ocorrência do título encontra-se dentro das ocorrências
   * informadas.
   * 
   * @param int $occurrences
   *   As ocorrências que se deseja pesquisar
   * 
   * @return bool
   */
  public function hasOccurrence(int ...$occurrences): bool
  {
    if (count($occurrences) == 0 && ! empty($this->getOccurrenceCode())) {
      // Não temos nenhuma ocorrência
      return true;
    }

    if (count($occurrences) == 1 && is_array($occurrences[0])) {
      // Foi informada uma única ocorrência e a mesma é uma matriz,
      // então usamos ela como o conjunto de ocorrências a ser
      // pesquisado
      $occurrences = $occurrences[0];
    }

    if (in_array($this->getOccurrenceCode(), $occurrences)) {
      return true;
    }

    return false;
  }

  /**
   * Obtém o código da ocorrência em um registro de transação.
   * 
   * @return int
   */
  public function getOccurrenceCode(): int
  {
    return $this->occurrenceCode;
  }

  /**
   * Define o código da ocorrência.
   * 
   * @param int $occurrenceCode
   *   O código da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceCode(int $occurrenceCode): self
  {
    $this->occurrenceCode = sprintf('%02s', $occurrenceCode);

    return $this;
  }

  /**
   * Obtém a descrição da ocorrência.
   * 
   * @return string
   */
  public function getOccurrenceDescription(): string
  {
    return $this->occurrenceDescription;
  }

  /**
   * Define a descrição da ocorrência.
   * 
   * @param string $occurrenceDescription
   *   A descrição da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceDescription(
    string $occurrenceDescription
  ): self
  {
    $this->occurrenceDescription = $occurrenceDescription;

    return $this;
  }

  /**
   * Obtém o código do tipo da ocôrrência.
   * 
   * @return int
   */
  public function getOccurrenceType(): int
  {
    return $this->occurrenceType;
  }

  /**
   * Define o código do tipo da ocorrência.
   * 
   * @param int $occurrenceType
   *   O tipo da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceType(int $occurrenceType): self
  {
    $this->occurrenceType = $occurrenceType;

    return $this;
  }

  /**
   * Obtém a data da ocorrência.
   * 
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getOccurrenceDate($format = 'd/m/Y')
  {
    return $this->occurrenceDate instanceof Carbon
      ? (($format === false)
        ? $this->occurrenceDate
        : $this->occurrenceDate->format($format))
      : null
    ;
  }

  /**
   * Define a data da ocorrência.
   * 
   * @param string $occurrenceDate
   *   A data da ocorrência
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceDate(
    string $occurrenceDate,
    string $format = 'dmy'
  ): self
  {
    $this->occurrenceDate = trim($occurrenceDate, '0 ')
      ? Carbon::createFromFormat($format, $occurrenceDate)
      : null
    ;

    return $this;
  }

  /**
   * Obtém o código da rejeição.
   * 
   * @return string
   */
  public function getRejectionReason(): string
  {
    return $this->rejectionReason ?: 0;
  }

  /**
   * Define o código da rejeição.
   * 
   * @param string $rejectionReason
   *   O código da rejeição
   *
   * @return $this
   *   A instância da transação
   */
  public function setRejectionReason(string $rejectionReason): self
  {
    $this->rejectionReason = $rejectionReason;

    return $this;
  }

  /**
   * Obtém a data de vencimento do título.
   * 
   * @param string $format
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getDueDate(string $format = 'd/m/Y')
  {
    return $this->dueDate instanceof Carbon
      ? (($format === false)
        ? $this->dueDate
        : $this->dueDate->format($format))
      : null
    ;
  }

  /**
   * Define a data de vencimento do título.
   * 
   * @param string $dueDate
   *   A data de vencimento do título
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return $this
   *   A instância da transação
   */
  public function setDueDate(
    string $dueDate,
    string $format = 'dmy'
  ): self
  {
    $this->dueDate = trim($dueDate, '0 ')
      ? Carbon::createFromFormat($format, $dueDate)
      : null
    ;

    return $this;
  }

  /**
   * Obtém a data em que o valor for creditado na conta.
   * 
   * @param string $format
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getCreditDate(string $format = 'd/m/Y')
  {
    return $this->creditDate instanceof Carbon
      ? (($format === false)
        ? $this->creditDate
        : $this->creditDate->format($format))
      : null
    ;
  }

  /**
   * Define a data em que o valor for creditado na conta.
   * 
   * @param string $dueDate
   *   A data em que o valor for creditado na conta
   * @param string $format (opcional)
   *   O formato da data
   *
   * @return $this
   *   A instância da transação
   */
  public function setcreditDate(
    string $creditDate,
    string $format = 'dmy'
  ): self
  {
    $this->creditDate = trim($creditDate, '0 ') ? Carbon::createFromFormat($format, $creditDate) : null;

    return $this;
  }

  /**
   * Obtém o valor do título.
   * 
   * @return float
   */
  public function getDocumentValue(): float
  {
    return $this->documentValue ?: 0.00;
  }

  /**
   * Define o valor do título.
   * 
   * @param float $documentValue
   *   O valor do título
   *
   * @return $this
   *   A instância da transação
   */
  public function setDocumentValue(float $documentValue): self
  {
    $this->documentValue = $documentValue;

    return $this;
  }

  /**
   * Obtém o valor da tarifa cobrada.
   * 
   * @return float
   */
  public function getTariffValue(): float
  {
    return $this->tariffValue ?: 0.00;
  }

  /**
   * Define o valor da tarifa cobrada.
   * 
   * @param float $tariffValue
   *   O valor da tarifa cobrada
   *
   * @return $this
   *   A instância da transação
   */
  public function setTariffValue(float $tariffValue): self
  {
    $this->tariffValue = $tariffValue;

    return $this;
  }

  /**
   * Obtém o valor cobrado a título de outras despesas.
   * 
   * @return float
   */
  public function getOtherExpensesValue(): float
  {
    return $this->otherExpensesValue ?: 0.00;
  }

  /**
   * Define o valor cobrado a título de outras despesas.
   * 
   * @param float $otherExpensesValue
   *   O valor cobrado a título de outras despesas
   *
   * @return $this
   *   A instância da transação
   */
  public function setOtherExpensesValue(
    float $otherExpensesValue
  ): self
  {
    $this->otherExpensesValue = $otherExpensesValue;

    return $this;
  }

  /**
   * Obtém o valor do IOF incidido sobre a transação.
   * 
   * @return float
   */
  public function getIOFValue(): float
  {
    return $this->iofValue ?? 0.00;
  }

  /**
   * Define o valor do IOF incidido sobre a transação.
   * 
   * @param float $iofValue
   *   O valor do IOF incidido sobre a transação
   *
   * @return $this
   *   A instância da transação
   */
  public function setIOFValue(float $iofValue): self
  {
    $this->iofValue = $iofValue;

    return $this;
  }

  /**
   * Obtém o valor de abatimento aplicado.
   * 
   * @return float
   */
  public function getAbatementValue(): float
  {
    return $this->abatementValue ?: 0.00;
  }

  /**
   * Define o valor de abatimento aplicado.
   * 
   * @param string $abatementValue
   *   O valor de abatimento aplicado
   *
   * @return $this
   *   A instância da transação
   */
  public function setAbatementValue(float $abatementValue): self
  {
    $this->abatementValue = $abatementValue;

    return $this;
  }

  /**
   * Obtém o valor de desconto aplicado.
   * 
   * @return float
   */
  public function getDiscountValue(): float
  {
    return $this->discountValue ?: 0.00;
  }

  /**
   * Define o valor de desconto aplicado.
   * 
   * @param float $discountValue
   *   O valor de desconto aplicado
   *
   * @return $this
   *   A instância da transação
   */
  public function setDiscountValue(float $discountValue): self
  {
    $this->discountValue = $discountValue;

    return $this;
  }

  /**
   * Obtém o valor efetivamento pago.
   * 
   * @return float
   */
  public function getPaidValue(): float
  {
    return $this->paidValue ?: 0.00;
  }

  /**
   * Define o valor efetivamento pago.
   * 
   * @param float $paidValue
   *   O valor efetivamento pago
   *
   * @return $this
   *   A instância da transação
   */
  public function setPaidValue(float $paidValue): self
  {
    $this->paidValue = $paidValue;

    return $this;
  }

  /**
   * Obtém o valor referente aos juros de mora cobrado.
   * 
   * @return float
   */
  public function getLatePaymentInterest(): float
  {
    return $this->latePaymentInterest ?: 0.00;
  }

  /**
   * Define o valor referente aos juros de mora cobrado.
   * 
   * @param float $latePaymentInterest
   *   O valor referente aos juros de mora cobrado
   *
   * @return $this
   *   A instância da transação
   */
  public function setLatePaymentInterest(
    float $latePaymentInterest
  ): self
  {
    $this->latePaymentInterest = $latePaymentInterest;

    return $this;
  }

  /**
   * Obtém o valor da multa aplicado ao título.
   * 
   * @return float
   */
  public function getFineValue(): float
  {
    return $this->fineValue ?: 0.00;
  }

  /**
   * Define o valor da multa aplicado ao título.
   * 
   * @param float $fineValue
   *   O valor da multa aplicado ao título
   *
   * @return $this
   *   A instância da transação
   */
  public function setFineValue(float $fineValue): self
  {
    $this->fineValue = $fineValue;

    return $this;
  }

  /**
   * Retorna se existe algum erro de rejeição do tírulo.
   *
   * @return bool
   */
  public function hasError(): bool
  {
    return $this->getOccurrenceCode() == BilletOccurrence::ERROR;
  }

  /**
   * Obtém os motivos da ocorrência.
   * 
   * @return array
   */
  public function getReasons(): array
  {
    $result = [];
    if ($this->reasons) {
      foreach ($this->reasons AS $value) {
        if ($value !== '') {
          $result[] = $value;
        }
      }
    }

    return $result;
  }

  /**
   * Define os motívos da ocorrência.
   * 
   * @param array $reasons
   *   Os motivos da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setReasons(array $reasons): self
  {
    $this->reasons = $reasons;

    return $this;
  }

  /**
   * Obtém a URL para o SPI do sistema PIX.
   * 
   * @return string
   */
  public function getSpiUrl(): string
  {
    return $this->spiUrl;
  }

  /**
   * Define a URL para o SPI do sistema PIX.
   * 
   * @param string $spiUrl
   *   A URL para o SPI do sistema PIX
   * 
   * @return $this
   *   A instância da transação
   */
  public function setSpiUrl(string $spiUrl): self
  {
    $this->spiUrl = $spiUrl;

    return $this;
  }

  /**
   * Obtém o ID da transação no sistema PIX.
   * 
   * @return string
   */
  public function getTxId(): string
  {
    return $this->txId;
  }

  /**
   * Define o ID da transação no sistema PIX.
   * 
   * @param string $txId
   * 
   * @return $this
   *   A instância da transação
   */
  public function setTxId(string $txId): self
  {
    $this->txId = $txId;

    return $this;
  }
}
