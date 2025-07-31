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
 * Uma interface que descreve um boleto bancário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;

use Carbon\Carbon;
use Core\Payments\FinancialAgent;

interface BankingBillet
{
  // =====[ Banco Emissor]==============================================

  /**
   * Obtém o código do banco emissor.
   *
   * @return string
   */
  public function getBankCode(): string;

  /**
   * Obtém o código do banco formatado.
   *
   * @return string
   */
  public function getFormattedBankCode(): string;
  
  /**
   * Obtém o código do banco com o dígito verificador.
   *
   * @return string
   */
  public function getBankCodeWithDAC(): string;
  
  /**
   * Obtém o nome do arquivo contendo a logomarca do banco.
   *
   * @return string
   */
  public function getBankLogo(): string;

  /**
   * Dados complementares do banco emissor.
   *
   * Em alguns bancos, a visualização de alguns campos do boleto são
   * diferentes. Nestes casos, sobrescreva este método na classe do
   * banco e retorne um array contendo estes campos alterados. Ex:
   *
   * <code>
   *   return array('carteira' => 'SR');
   * </code>
   *
   * Mostrará SR no campo "Wallet" do boleto.
   * 
   * Mas também permite utilizar campos personalizados, por exemplo,
   * caso exista o campo ABC no boleto, você pode defini-lo na classe do
   * banco, retornar o valor dele através deste método e mostrá-lo na
   * view correspondente.
   *
   * @return array
   */
  public function getComplementaryData(): array;


  // =====[ Informações do beneficiário (emissor) ]=====================
  
  /**
   * Define o beneficiário (o emissor).
   *
   * @param FinancialAgent $emitterEntity
   *   A entidade emissora do título
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setEmitter(
    FinancialAgent $emitterEntity
  ): self;

  /**
   * Obtém o beneficiário (emissor).
   *
   * @return FinancialAgent
   */
  public function getEmitter(): ?FinancialAgent;

  /**
   * Obtém o número da agência do beneficiário (emissor) formatado
   * conforme os padrões do banco.
   *
   * @return string
   */
  public function getAgencyNumber(): string;
  
  /**
   * Obtém o número da conta do beneficiário (emissor) formatado
   * conforme os padrões do banco.
   *
   * @return string
   */
  public function getAccountNumber(): string;
  
  /**
   * Obtém a agência e número da conta do beneficiário (emissor) do
   * boleto formatado conforme os padrões do banco.
   *
   * @return string
   */
  public function getAgencyNumberAndEmitterCode(): string;


  // =====[ Informações do pagante ]====================================
  
  /**
   * Define o pagador.
   *
   * @param FinancialAgent $payerEntity
   *   Os dados do pagador
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setPayer(FinancialAgent $payerEntity): self;

  /**
   * Obtém os dados do pagador.
   *
   * @return FinancialAgent
   */
  public function getPayer(): ?FinancialAgent;


  // =====[ Informações do Avalista ]===================================
  
  /**
   * Define os dados da entidade sacador/avalista do boleto.
   *
   * @param FinancialAgent $guarantorEntity
   *   Os dados da entidade sacador/avalista
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setGuarantor(
    FinancialAgent $guarantorEntity
  ): self;

  /**
   * Obtém os dados da entidade sacador/avalista.
   *
   * @return FinancialAgent
   */
  public function getGuarantor(): ?FinancialAgent;


  // =====[ Informações do contrato com o banco emissor ]===============
  
  /**
   * Define o código da carteira (com ou sem registro).
   *
   * @param string $wallet
   *   O código da carteira
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um código de carteira inválido
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setWallet(string $wallet): self;

  /**
   * Obtém o código da carteira (com ou sem registro).
   *
   * @return string
   */
  public function getWallet(): ?string;
  
  /**
   * Obtém o nome da carteira para impressão no boleto.
   * 
   * Caso o nome da carteira a ser impresso no boleto seja diferente do
   * número, então precisa ser criada uma variável na classe do banco
   * correspondente $walletsNames como uma matriz cujos índices sejam os
   * respectivos números das carteiras e os valores seus respectivos
   * nomes.
   *
   * @return string
   */
  public function getWalletName(): string;

  /**
   * Define a numeração do título, que é um número sequencial que é
   * incrementado a medida que os mesmos forem sendo ingressados no
   * sistema, e que não pode se repetir.
   * 
   * @param int $sequentialNumber
   *   Número sequencial
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setSequentialNumber(
    int $sequentialNumber
  ): self;
  
  /**
   * Obtém a numeração do título, que é um número sequencial incrementado
   * a cada emissão e que não pode se repetir.
   *
   * @return int
   */
  public function getSequentialNumber(): ?int;


  // =====[ Informações do documento ]==================================

  /**
   * Define a data do documento. É a data em que o documento foi gerado
   * e cujo valor está sendo cobrado por este boleto.
   *
   * @param Carbon $dateOfDocument
   *   A data do documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDateOfDocument(
    Carbon $dateOfDocument
  ): self;

  /**
   * Obtém a data do documento.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfDocument();
  
  /**
   * Define a espécie de documento, geralmente DM (Duplicata Mercantil).
   *
   * @param string $kindOfDocument
   *   A espécie de documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setKindOfDocument(
    string $kindOfDocument
  ): self;

  /**
   * Obtém a espécie de documento.
   *
   * @return string
   */
  public function getKindOfDocument(): string;

  /**
   * Obtém o nome da espécie de documento.
   *
   * @throws InvalidArgumentException
   *   Em caso da espécie de documento não ter sido definida
   *
   * @return string
   */
  public function getKindOfDocumentName(): string;
  
  /**
   * Define o número do documento.
   *
   * @param string $documentNumber
   *   O número do documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDocumentNumber(
    $documentNumber
  ): self;

  /**
   * Obtém o número do documento.
   *
   * @return string
   */
  public function getDocumentNumber(): string;

  /**
   * Define a informação de aceite ('S' para 'Sim' e 'N' para 'Não'). O
   * aceite é o reconhecimento da dívida pelo devedor, pela aposição de
   * assinatura no título entregue para cobrança. O padrão é 'N'
   *
   * @param string $acceptance
   *   A informação de aceite
   *
   * @throws InvalidArgumentException
   *   Em caso de aceite inválido
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAcceptance(
    string $acceptance
  ): self;

  /**
   * Obtém a informação de aceite ('A' para 'Aceito' e 'N' para 'Não').
   *
   * @return string
   */
  public function getAcceptance(): string;
  
  /**
   * Define a informação do código do tipo de moeda que o documento foi
   * emitido (R$, US$, IGPM etc.). O padrão é o Real (R$).
   *
   * @param int $currency
   *   O código do tipo de moeda
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setCurrency(int $currency): self;

  /**
   * Obtém a informação do código do tipo de moeda na qual o documento
   * foi emitido (R$, US$, IGPM etc.).
   *
   * @return int
   */
  public function getCurrency(): int;

  /**
   * Define a quantidade a correspondente da moeda quando o documento é
   * emitido em moeda indexada (US$, IGPM etc.). Este campo permite
   * informar o valor quando a moeda não for o REAL, já que para estes
   * casos, é necessário fazer a conversão pela cotação do dia, o que
   * irá resultar no valor final do documento.
   *
   * @param float $amount
   *   A quantidade na moeda utilizada
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAmount(float $amount): self;
  
  /**
   * Obtém a quantidade a correspondente da moeda quando o documento é
   * emitido em moeda indexada (US$, IGPM etc.).
   *
   * @return float
   */
  public function getAmount(): float;

  /**
   * Define o valor do documento. O valor do documento deve ser
   * informado sempre que a moeda for o REAL e o boleto não for
   * contra-apresentação.
   *
   * @param float $documentValue
   *   O valor do documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDocumentValue(
    float $documentValue
  ): self;

  /**
   * Obtém o valor do documento. O valor do documento sempre é em branco
   * quando o boleto for contra-apresentação. Normalmente também é
   * deixado em branco quando o boleto estiver em moeda estrangeira e o
   * valor da cotação da moeda (valor unitário) não tiver sido informado
   * antes. Caso contrário, utiliza o valor informado.
   *
   * @return float
   */
  public function getDocumentValue(): float;
  
  /**
   * Define uma matriz com a descrição do demonstrativo, com no máximo
   * 5 linhas.
   *
   * @param mixed $demonstrativeText
   *   O texto ou matriz contendo a descrição do demonstrativo
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado uma descrição do demonstrativo inválida
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDemonstrativeText(
    $demonstrativeText
  ): self;
  
  /**
   * Obtém uma matriz com a descrição do que se está cobrando através
   * deste documento (demonstrativo).
   *
   * @return string[]
   *   A descrição do demonstrativo com no máximo 5 linhas
   */
  public function getDemonstrativeText(): array;

  /**
   * Define o número da parcela.
   *
   * @param int $parcel
   *   O número da parcela
   * @param int $total
   *   A quantidade de parcelas
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setParcel(
    int $parcel,
    int $total
  ): self;

  /**
   * Obtém a informação de parcelamento.
   *
   * @return string
   *   A informação da parcela
   */
  public function getParcel(): string;

  /**
   * Define o mês de referência do qual está sendo cobrado neste
   * documento.
   *
   * @param string $referenceMonthYear
   *   O mês e ano de referência
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setReferenceMonth(
    string $referenceMonthYear = null
  ): self;
  
  /**
   * Obtém o mês de referência do qual está sendo cobrado neste
   * documento.
   *
   * @return string
   *   O mês de referência
   */
  public function getReferenceMonth(): string;

  /**
   * Define o totalizador de itens.
   *
   * @param int $total
   *   A quantidade de itens
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setTotalizer(int $total): self;

  /**
   * Obtém o totalizador de itens que estão sendo cobrado neste
   * documento.
   *
   * @return int
   *   A quantidade de itens
   */
  public function getTotalizer(): int;

  /**
   * Define uma matriz com o histórico de operações para extratos, com
   * no máximo 8 linhas.
   *
   * @param mixed $historicText
   *   O texto ou matriz contendo a descrição do histórico de operações
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado uma descrição do demonstrativo inválida
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setHistoric($historicText): self;
  
  /**
   * Obtém uma matriz com o histórico de operações as quais estão sendo
   * cobradas neste documento.
   *
   * @return string[]
   *   O histórico de operações com no máximo 8 linhas
   */
  public function getHistoric(): array;


  // =====[ Informações para registro ]=================================

  /**
   * Obtém o código da espécie de documento.
   *
   * @param int $default
   *   O valor padrão
   * @param int $cnabType
   *   O tipo do registro CNAB
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um tipo CNAB inválido
   *
   * @return string
   *   O código da espécie de documento
   */
  public function getKindOfDocumentCode(int $default = 99,
    int $cnabType = 240): string;

  /**
   * Define o número de controle interno (da aplicação) para controle da
   * remessa.
   *
   * @param string $controlNumber
   *   O número de controle interno
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setControlNumber(
    $controlNumber
  ): self;

  /**
   * Obtém o número de controle interno (da aplicação) para controle da
   * remessa.
   *
   * @return string
   */
  public function getControlNumber(): string;

  /**
   * Define a instrução a ser realizada em relação ao boleto.
   * 
   * @param int $billetInstruction
   *   A instrução a ser executada em relação ao boleto
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setBilletInstruction(
    int $billetInstruction
  ): self;

  /**
   * Obtém a instrução a ser realizada em relação ao boleto.
   *
   * @return int
   */
  public function getBilletInstruction(): int;
  
  /**
   * Define o envio de uma instrução personalizada para o boleto.
   * 
   * @param int $instructionCode
   *   O código da instrução personalizada
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setCustomInstruction(
    int $instructionCode
  ): self;
  
  /**
   * Define parâmetros adicionais específicos de um banco necessários
   * para geração do boleto.
   * 
   * @param array $params
   *   Os parâmetros adicionais
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAdditionalParameters(
    array $params = []
  ): self;

  /**
   * Obtém a instrução personalizada a ser aplicada no boleto.
   *
   * @return int|null
   */
  public function getCustomInstruction(): ?int;


  // =====[ Informações da cobrança ]===================================

  /**
   * Define a data de geração do boleto (data de processamento).
   *
   * @param Carbon $dateOfProcessing
   *   A data de geração do boleto
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDateOfProcessing(
    Carbon $dateOfProcessing
  ): self;
  
  /**
   * Obtém a data de geração do boleto (Data de processamento).
   *
   * @return Carbon\Carbon
   */
  public function getDateOfProcessing();

  /**
   * Define se o boleto é contra-apresentação, ou seja, a data de
   * vencimento e o valor são deixados em branco. Neste caso, sugere-se
   * que defina-se um valor para o pagamento mínimo.
   *
   * @param bool $againstPresentation
   *   A informação se é ou não contra-apresentação
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAgainstPresentation(
    bool $againstPresentation
  ): self;

  /**
   * Obtém se o boleto é contra-apresentação, ou seja, a data de
   * vencimento é indefinida.
   *
   * @return bool
   */
  public function getAgainstPresentation(): bool;

  /**
   * Define valor para pagamento mínimo em boletos de contra
   * apresentação. Isto é necessário pois, quando a flag
   * contra-apresentação é definida, o valor do boleto é suprimido.
   *
   * @param float $minimumPayment
   *   O valor para pagamento mínimo
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setMinimumPayment(
    float $minimumPayment
  ): self;

  /**
   * Obtém o valor para pagamento mínimo em boletos de contra
   * apresentação.
   *
   * @return float
   */
  public function getMinimumPayment(): float;

  /**
   * Define a data de vencimento do boleto.
   *
   * @param Carbon $dateOfExpiration
   *   A data de vencimento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDateOfExpiration(
    Carbon $dateOfExpiration
  ): self;

  /**
   * Obtém a data de vencimento do boleto.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfExpiration();

  /**
   * Define a data limite para concessão de desconto no valor do
   * documento.
   *
   * @param Carbon $dateOfDiscount
   *   A data do documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDateOfDiscount(
    Carbon $dateOfDiscount
  ): self;
  
  /**
   * Obtém a data limite para concessão de desconto no valor do
   * documento.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfDiscount();

  /**
   * Define o valor do desconto.
   *
   * @param float $discount
   *   O valor do desconto a ser aplicado ao valor, se o mesmo for pago
   *   antes da data indicada.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setDiscountValue(
    float $discount
  ): self;

  /**
   * Obtém o valor do desconto.
   *
   * @return float
   */
  public function getDiscountValue(): float;
  
  /**
   * Define a quantidade de dias após o vencimento em que se inicia a
   * cobrança de juros.
   *
   * @param int $days
   *   A quantidade de dias após o vencimento em que se inicia a
   *   cobrança de juros
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setStartChargingInterestAfter(
    int $days
  ): self;
  
  /**
   * Obtém a quantidade de dias após o vencimento em que se inicia a
   * cobrança de juros.
   *
   * @return int
   */
  public function getStartChargingInterestAfter(): int;

  /**
   * Retorna a data de início da incidência de juros sobre o valor do
   * documento.
   *
   * @return \Carbon\Carbon
   */
  public function getStartDateOfChargingInterest();
  
  /**
   * Define o tipo do valor dos juros de mora.
   *
   * @param int $arrearInterestType
   *   O tipo do valor dos juros de mora
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setArrearInterestType(
    int $arrearInterestType
  ): self;

  /**
   * Obtém o tipo do valor dos juros de mora.
   *
   * @return int
   */
  public function getArrearInterestType(): int;
  
  /**
   * Define o valor dos juros de mora por dia de atraso.
   *
   * @param float $arrearInterestPerDay
   *   O valor dos juros de mora a serem aplicados por dia de atraso
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setArrearInterestPerDay(
    float $arrearInterestPerDay
  ): self;
  
  /**
   * Obtém o valor dos juros de mora por dia de atraso a serem
   * aplicados.
   *
   * @return float
   */
  public function getArrearInterestPerDay(): float;
  
  /**
   * Define o valor da multa.
   *
   * @param float $fineValue
   *   O valor dos juros de mora e/ou multa a serem aplicados
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setFineValue(
    float $fineValue
  ): self;
  
  /**
   * Obtém o valor da multa.
   *
   * @return float
   */
  public function getFineValue(): float;
  
  /**
   * Define a instrução para pré-determinar o protesto/negativação do
   * título e/ou a baixa por decurso de prazo, quando do registro.
   *
   * @param int $instruction
   *   A instrução a ser adotada
   * @param int $days
   *   A quantidade de dias em que a instrução passa a vigorar
   *
   * @throws InvalidArgumentException
   *   Em caso de parâmetro inválido
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setInstructionAfterExpiration(
    int $instruction,
    int $days
  ): self;
  
  /**
   * Obtém a instrução para pré-determinar o protesto/negativação do
   * título e/ou a baixa por decurso de prazo, quando do registro.
   *
   * @return int
   */
  public function getInstructionAfterExpiration(): int;
  
  /**
   * Obtém a quantidade de dias após o vencimento em que a instrução
   * deve ocorrer.
   *
   * @return int
   */
  public function getInstructionDays(): int;

  /**
   * Define a informação de uso exclusivo do banco. Neste campo é
   * impressa informação cujo conteúdo varia de instituição para
   * instituição.
   *
   * @param string $forBankUse
   *   O valor a ser incluído no campo "uso do banco"
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setForBankUse(
    string $forBankUse
  ): self;
  
  /**
   * Obtém a informação de uso exclusivo do banco.
   *
   * @return string
   */
  public function getForBankUse(): ?string;

  /**
   * Define uma matriz com as instruções para pagamento, com no máximo 8
   * linhas. Linhas adicionais serão ignoradas.
   *
   * @param mixed $instructionsText
   *   O texto ou linhas de texto com as instruções
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um valor para o texto de instrução inválido
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setInstructionsText(
    $instructionsText
  ): self;
  
  /**
   * Define uma matriz com as instruções para pagamento automaticamente
   * com base nos valores informados.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAutoInstructionsText(): self;
  
  /**
   * Obtém uma matriz com as instruções para pagamento.
   *
   * @return string[]
   *   As instruções para pagamento com no máximo 8 linhas
   */
  public function getInstructionsText(): array;

  /**
   * Define uma matriz com as instruções para impressão, com no máximo 5
   * linhas. Linhas adicionais serão ignoradas.
   *
   * @param mixed $instructionsText
   *   O texto ou linhas de texto com as instruções de impressão
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um valor para o texto de instrução inválido
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setPrintInstructionsText(
    $instructionsText
  ): self;
  
  /**
   * Obtém uma matriz com as instruções para impressão.
   *
   * @return string[]
   *   As instruções para impressão com no máximo 5 linhas
   */
  public function getPrintInstructionsText(): array;

  /**
   * Define o local de pagamento do boleto.
   *
   * @param string $paymentPlace
   *   O local de pagamento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setPaymentPlace(
    string $paymentPlace
  ): self;
  
  /**
   * Obtém o local de pagamento do boleto.
   *
   * @return string
   */
  public function getPaymentPlace(): string;


  // =====[ Informações calculadas ]====================================
  
  /**
   * Obtém o número de identificação do título no banco ("nosso número").
   * O número do título é a principal chave de acesso ao registro deste
   * boleto no banco. Os títulos registrados recebem uma numeração
   * constituída de dígitos que modificam de instituição para
   * instituição. Este valor é gerado através da função buildBankIdentificationNumber().
   *
   * @return string
   */
  public function getBankIdentificationNumber(): string;

  /**
   * Obtém o número de identificação do título no banco ("nosso número")
   * a ser utilizado no boleto. Algumas instituições possuem diferença
   * entre este valor e o que é usado na transmissão.
   *
   * @return string
   */
  public function getBankIdentificationNumberForBillet(): string;
  
  /**
   * Obtém o número do código de barras segundo o padrão FEBRABAN.
   *
   * @return string
   */
  public function getBarCodeNumber(): string;
  
  /**
   * Obtém a linha digitável do boleto.
   *
   * @return string
   */
  public function getDigitableLine(): string;

  /**
   * Método que determina se foram preenchidos todos os campos que são
   * obrigatórios para emissão do boleto.
   *
   * @param $messages
   *
   * @return boolean
   */
  public function isValid(&$messages);
}
