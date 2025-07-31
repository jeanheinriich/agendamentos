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
 * A interface para uma transação de um arquivo de retorno no padrão
 * Cnab da FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning;

interface TransactionInterface
{
  /**
   * Obtém a carteira.
   * 
   * @return string
   */
  public function getWallet(): string;

  /**
   * Define a carteira.
   * 
   * @param string $wallet
   *   A carteira
   *
   * @return $this
   *   A instância da transação
   */
  public function setWallet(string $wallet): object;

  /**
   * Obtém o número de identificação do título no banco ("nosso número"),
   * baseado nas regras da instituição pela qual o boleto foi emitido.
   * 
   * @return string
   */
  public function getBankIdentificationNumber(): string;

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
  ): object;

  /**
   * Obtém o número do documento.
   * 
   * @return string
   */
  public function getDocumentNumber(): string;

  /**
   * Define o número do documento.
   * 
   * @param string $documentNumber
   *   O número do documento
   *
   * @return $this
   *   A instância da transação
   */
  public function setDocumentNumber(string $documentNumber): object;

  /**
   * Obtém o número de controle interno (da aplicação) para controle da
   * remessa.
   * 
   * @return string
   */
  public function getControlNumber(): string;

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
  public function setControlNumber(string $controlNumber): object;

  /**
   * Obtém o código de liquidação do título.
   *
   * @return int
   */
  public function getSettlementCode(): int;

  /**
   * Define o código de liquidação do título.
   *
   * @param int $settlementCode
   *   O código de liquidação do título
   *
   * @return $this
   *   A instância da transação
   */
  public function setSettlementCode(int $settlementCode): object;

  /**
   * Obtém se a ocorrência do título encontra-se dentro das ocorrências
   * informadas.
   * 
   * @param int $occurrences
   *   As ocorrências que se deseja pesquisar
   * 
   * @return bool
   */
  public function hasOccurrence(int ...$occurrences): bool;

  /**
   * Obtém o código da ocorrência em um registro de transação.
   * 
   * @return int
   */
  public function getOccurrenceCode(): int;

  /**
   * Define o código da ocorrência.
   * 
   * @param int $occurrenceCode
   *   O código da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceCode(int $occurrenceCode): object;

  /**
   * Obtém a descrição da ocorrência.
   * 
   * @return string
   */
  public function getOccurrenceDescription(): string;

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
  ): object;

  /**
   * Obtém o código do tipo da ocorrência.
   * 
   * @return int
   */
  public function getOccurrenceType(): int;

  /**
   * Define o código do tipo da ocorrência.
   * 
   * @param int $occurrenceType
   *   O tipo da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setOccurrenceType(int $occurrenceType): object;

  /**
   * Obtém a data da ocorrência.
   * 
   * @param string $format
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getOccurrenceDate(string $format = 'd/m/Y');

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
  ): object;

  /**
   * Obtém o código da rejeição.
   * 
   * @return string
   */
  public function getRejectionReason(): string;

  /**
   * Define o código da rejeição.
   * 
   * @param string $rejectionReason
   *   O código da rejeição
   *
   * @return $this
   *   A instância da transação
   */
  public function setRejectionReason(string $rejectionReason): object;

  /**
   * Obtém a data de vencimento do título.
   * 
   * @param string $format
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getDueDate(string $format = 'd/m/Y');

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
  ): object;

  /**
   * Obtém a data em que o valor for creditado na conta.
   * 
   * @param string $format
   *   O formato da data
   *
   * @return Carbon|string|null
   */
  public function getCreditDate(string $format = 'd/m/Y');

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
  ): object;

  /**
   * Obtém o valor do título.
   * 
   * @return float
   */
  public function getDocumentValue(): float;

  /**
   * Define o valor do título.
   * 
   * @param float $documentValue
   *   O valor do título
   *
   * @return $this
   *   A instância da transação
   */
  public function setDocumentValue(float $documentValue): object;

  /**
   * Obtém o valor da tarifa cobrada.
   * 
   * @return float
   */
  public function getTariffValue(): float;

  /**
   * Define o valor da tarifa cobrada.
   * 
   * @param float $tariffValue
   *   O valor da tarifa cobrada
   *
   * @return $this
   *   A instância da transação
   */
  public function setTariffValue(float $tariffValue): object;

  /**
   * Obtém o valor cobrado a título de outras despesas.
   * 
   * @return float
   */
  public function getOtherExpensesValue(): float;

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
  ): object;

  /**
   * Obtém o valor do IOF incidido sobre a transação.
   * 
   * @return float
   */
  public function getIOFValue(): float;

  /**
   * Define o valor do IOF incidido sobre a transação.
   * 
   * @param float $iofValue
   *   O valor do IOF incidido sobre a transação
   *
   * @return $this
   *   A instância da transação
   */
  public function setIOFValue(float $iofValue): object;

  /**
   * Obtém o valor de abatimento aplicado.
   * 
   * @return float
   */
  public function getAbatementValue(): float;

  /**
   * Define o valor de abatimento aplicado.
   * 
   * @param string $abatementValue
   *   O valor de abatimento aplicado
   *
   * @return $this
   *   A instância da transação
   */
  public function setAbatementValue(float $abatementValue): object;

  /**
   * Obtém o valor de desconto aplicado.
   * 
   * @return float
   */
  public function getDiscountValue(): float;

  /**
   * Define o valor de desconto aplicado.
   * 
   * @param float $discountValue
   *   O valor de desconto aplicado
   *
   * @return $this
   *   A instância da transação
   */
  public function setDiscountValue(float $discountValue): object;

  /**
   * O valor efetivamento pago.
   * 
   * @return float
   */
  public function getPaidValue(): float;

  /**
   * Define o valor efetivamento pago.
   * 
   * @param float $paidValue
   *   O valor efetivamento pago
   *
   * @return $this
   *   A instância da transação
   */
  public function setPaidValue(float $paidValue): object;

  /**
   * Obtém o valor referente aos juros de mora cobrado.
   * 
   * @return float
   */
  public function getLatePaymentInterest(): float;

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
  ): object;

  /**
   * Obtém o valor da multa aplicado ao título.
   * 
   * @return float
   */
  public function getFineValue(): float;

  /**
   * Define o valor da multa aplicado ao título.
   * 
   * @param float $fineValue
   *   O valor da multa aplicado ao título
   *
   * @return $this
   *   A instância da transação
   */
  public function setFineValue(float $fineValue): object;

  /**
   * Retorna se existe algum erro de rejeição do tírulo.
   *
   * @return bool
   */
  public function hasError(): bool;

  /**
   * Obtém os motivos da ocorrência.
   * 
   * @return string
   */
  public function getReasons(): array;

  /**
   * Define os motívos da ocorrência.
   * 
   * @param array $reasons
   *   Os motivos da ocorrência
   *
   * @return $this
   *   A instância da transação
   */
  public function setReasons(array $reasons): object;

  /**
   * Obtém a URL para o SPI do sistema PIX.
   * 
   * @return string
   */
  public function getSpiUrl(): string;

  /**
   * Define a URL para o SPI do sistema PIX.
   * 
   * @param string $spiUrl
   *   A URL para o SPI do sistema PIX
   * 
   * @return $this
   *   A instância da transação
   */
  public function setSpiUrl(string $spiUrl): object;

  /**
   * Obtém o ID da transação no sistema PIX.
   * 
   * @return string
   */
  public function getTxId(): string;

  /**
   * Define o ID da transação no sistema PIX.
   * 
   * @param string $txId
   * 
   * @return $this
   *   A instância da transação
   */
  public function setTxId(string $txId): object;

  /**
   * Converte o conteúdo do fechamento para matriz.
   * 
   * @return array
   */
  public function toArray(): array;
}