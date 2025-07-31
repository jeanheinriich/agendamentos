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
 * Uma classe abstrata para servir como base para geradores de boletos
 * bancários com base no padrão FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;

use Carbon\Carbon;
use Core\Helpers\InterpolateTrait;
use Core\Payments\Cnab\BilletInstruction;
use Core\Payments\Cnab\Instructions;
use Core\Payments\Coins;
use Core\Payments\FinancialAgent;

use InvalidArgumentException;
use RuntimeException;

abstract class AbstractBankingBillet
  implements BankingBillet, RegisterableBillet
{
  /**
   * Os métodos para formatação
   */
  use FormatterTrait;

  /**
   * Os métodos para cálculo de dígito verificador
   */
  use CheckSumTrait;

  /**
   * Os métodos para substituir o valor da variável na mensagem
   */
  use InterpolateTrait;

  /**
   * Data-base da FEBRABAN para cálculo do fator de vencimento, que é
   * usado para determinar a data de vencimento no código de barras.
   *
   * @cont string
   */
  const DUE_ON_BASE_DATE = '1997-10-07 00:00:00';
  const DUE_ON_NEW_BASE_DATE = '2025-02-22 00:00:00';

  /**
   * Campos que são necessários para emissão do boleto.
   *
   * @var array
   */
  private $requiredFields = [
    'sequentialNumber',
    'emitter',
    'payer',
    'wallet'
  ];

  /**
   * Os campos que não podemos modificar através de 'set' ou durante a
   * inicialização desta classe.
   *
   * @var array
   */
  protected $protectedFields = [
      'bankIdentificationNumber',
  ];


  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 0;
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage;
  
  /**
   * Cache para o campo livre.
   *
   * @var string
   */
  protected $freeField;


  // -----[ Informações do beneficiário (emissor) ]---------------------

  /**
   * A entidade beneficiária (quem emite o boleto).
   *
   * @var FinancialAgent
   */
  protected $emitterEntity;


  // -----[ Informações do pagante ]------------------------------------
  
  /**
   * A entidade pagadora (de quem se cobra o boleto).
   *
   * @var FinancialAgent
   */
  protected $payerEntity;


  // -----[ Informações do avalista ]-----------------------------------
  
  /**
   * Entidade sacador avalista (quem é o garantidor).
   *
   * @var FinancialAgent
   */
  protected $guarantorEntity;


  // -----[ Informações do contrato com o banco emissor ]---------------

  /**
   * Carteira ou modalidade de cobrança que a empresa opera no banco,
   * geralmente cobrança simples ou registrada.
   *
   * @var string
   */
  protected $wallet;
  
  /**
   * Define as carteiras disponíveis para cada banco.
   *
   * @var array
   */
  protected $wallets = [];
  
  /**
   * Define os nomes das carteiras disponíveis para cada banco.
   *
   * @var array
   */
  protected $walletsNames = [];

  /**
   * Define a numeração do título, que é um número sequencial que é
   * incrementado a medida que os mesmos forem sendo ingressados no
   * sistema, e que não pode se repetir. Este número é usado para compor
   * o número de identificação do título no banco (nosso número).
   *
   * @var int
   */
  protected $sequentialNumber;


  // -----[ Informações do documento ]----------------------------------

  /**
   * A data de emissão do documento, ou a data do faturamento. É usado
   * para informar a data em que o documento cujo valor está sendo
   * cobrado através do boleto foi gerado.
   *
   * @var \Carbon\Carbon
   */
  protected $dateOfDocument;

  /**
   * As descrições das espécies de documentos.
   *
   * @var string[]
   */
  protected $kindsOfDocuments = [
    'DM'  => 'Duplicata mercantil',
    'NP'  => 'Nota promissória',
    'NS'  => 'Nota de seguro',
    'CS'  => 'Cobrança seriada',
    'REC' => 'Recibo',
    'LC'  => 'Letras de câmbio',
    'ND'  => 'Nota de débito',
    'DS'  => 'Duplicata de serviços, outros'
  ];

  /**
   * Espécie de documento, geralmente DM (Duplicata Mercantil). Caso não
   * informada, não exibe a informação no boleto. De acordo com o ramo
   * de atividade, poderão ser utilizadas uma das siglas:
   *   DM : Duplicata mercantil,
   *   NP : Nota promissória,
   *   NS : Nota de seguro,
   *   CS : Cobrança seriada,
   *   REC: Recibo,
   *   LC : Letras de câmbio,
   *   ND : Nota de débito,
   *   DS : Duplicata de serviços, outros
   *
   * @var string
   */
  protected $kindOfDocument = 'DM';
  
  /**
   * Número do documento. É usado para informar o número do documento
   * (a identificação do número da fatura, duplicata, etc) cujo valor
   * está sendo cobrado neste boleto.
   *
   * @var string
   */
  protected $documentNumber;

  /**
   * A informação de aceite ('S' para 'Sim' e 'N' para 'Não'). O aceite
   * é o reconhecimento da dívida pelo devedor, pela aposição de
   * assinatura no título entregue para cobrança. O padrão é 'N'.
   *
   * @var string
   */
  protected $acceptance = 'N';

  /**
   * A informação do tipo de moeda que o documento foi emitido (R$, US$,
   * IGPM etc.). O padrão é o Real (R$).
   * 
   * @const int
   */
  protected $currency = Coins::CURRENCY_BRAZILIAN_REAL;

  /**
   * Quando o documento for emitido em moeda indexada (US$, IGPM etc.),
   * preencher esse campo com a quantidade correspondente.
   *
   * @var float
   */
  protected $amount = 0.00;

  /**
   * O valor do documento.
   *
   * @var float
   */
  protected $documentValue = 0.00;

  /**
   * As linhas do demonstrativo (descrição do pagamento) que aparece na
   * parte superior da folha do boleto.
   *
   * @var string[]
   */
  protected $demonstrativeText = [];

  /**
   * O totalizador de itens presentes na fatura.
   *
   * @var int
   */
  protected $totalizer = 0;

  /**
   * O número da parcela.
   *
   * @var int
   */
  protected $parcel = 0;

  /**
   * O número de parcelas.
   *
   * @var int
   */
  protected $numberOfParcels = 0;

  /**
   * O mês e ano de referência para a cobrança que está sendo realizada.
   *
   * @var string
   */
  protected $referenceMonthYear;

  /**
   * O histórico de operações para extrato (com no máximo 8 linhas).
   *
   * @var string[]
   */
  protected $historic = [];


  // -----[ Informações da cobrança ]-----------------------------------
  
  /**
   * A data de geração do boleto (data de processamento). Se não
   * informada, considera-se o dia corrente.
   *
   * @var \Carbon\Carbon
   */
  protected $dateOfProcessing;

  /**
   * Define se o boleto é para contra-apresentação.
   *
   * @var boolean
   */
  protected $againstPresentation = false;
  
  /**
   * Valor para pagamento mínimo (para uso em boletos definidos para
   * pagamento contra apresentação).
   *
   * @var float
   */
  protected $minimumPayment = 0.00;
  
  /**
   * A data de vencimento. É usado para informar a data em que o boleto
   * vence, e que, se ultrapassada, pode gerar multas e juros.
   *
   * @var \Carbon\Carbon
   */
  protected $dateOfExpiration;

  /**
   * A data limite para concessão de desconto no valor do boleto.
   *
   * @var \Carbon\Carbon
   */
  protected $dateOfDiscount;

  /**
   * Valor do desconto.
   *
   * @var float
   */
  protected $discountValue = 0.00;

  /**
   * A quantidade de dias após o vencimento em que se inicia a cobrança
   * de juros. O padrão é no dia seguinte.
   *
   * @var int
   */
  protected $startChargingInterestAfter = 1;
  
  /**
   * Tipo dos juros de mora.
   *
   * @var integer
   */
  protected $arrearInterestType = 2;
  
  /**
   * Valor para juros de mora por dia de atraso.
   *
   * @var float
   */
  protected $arrearInterestPerDay = 0.00;
  
  /**
   * Valor para multa.
   *
   * @var float
   */
  protected $fineValue = 0.00;

  /**
   * A instrução a ser adotada pelo banco após o vencimento do boleto.
   *
   * @var int
   */
  protected $instructionAfterExpiration = 0;

  /**
   * A quantidade de dias após o vencimento em que a instrução deve ser
   * executada.
   *
   * @var integer
   */
  protected $instructionDays = 0;

  /**
   * A quantidade de dias após o vencimento em que o título é registrado
   * no pagador é
   * negativado, .
   *
   * @var integer
   */
  protected $negativeAfter = 0;

  /**
   * A quantidade de dias após o vencimento em que ocorre a baixa
   * automática do título por decurso de prazo.
   *
   * @var integer
   */
  protected $goDownAfter = 0;

  /**
   * Informações de uso exclusivo do banco. Neste campo é impressa
   * informação cujo conteúdo varia de instituição para instituição.
   *
   * @var string
   */
  protected $forBankUse;

  /**
   * Linhas de instruções. Estas informações serão utilizadas pelo caixa
   * para incluir valores a serem cobrados no momento, bem como para
   * aceitar ou não o pagamento caso o boleto esteja vencido.
   *
   * @var string[]
   */
  protected $instructionsText = [
    'Pagar até a data do vencimento.'
  ];

  /**
   * Linhas de instruções da impressão do boleto (até 5 linhas).
   *
   * @var array
   */
  protected $printInstructionsText = [
    'Imprima em impressora jato de tinta (ink jet) ou laser em qualidade normal ou alta (Não use modo econômico).',
    'Utilize folha A4 (210 x 297 mm) ou Carta (216 x 279 mm) e margens mínimas à esquerda e à direita do formulário.',
    'Corte na linha indicada. Não rasure, risque, fure ou dobre a região onde se encontra o código de barras.',
    'Caso tenha problemas ao imprimir, copie a sequencia numérica abaixo e pague no caixa eletrônico ou no internet banking.'
  ];

  /**
   * O campo destinado à inserção de mensagem para indicar ao pagador
   * onde o pagamento poderá ser efetuado.
   *
   * @var string
   */
  protected $paymentPlace = 'Pagável em qualquer agência bancária até '
    . 'o vencimento.'
  ;


  // -----[ Informações para registro ]---------------------------------

  /**
   * Número de controle interno (da aplicação) para controle da remessa.
   *
   * @var string
   */
  protected $controlNumber;

  /**
   * Códigos de espécies de documento, para uso na remessa.
   *
   * @var array
   */
  protected $kindOfDocumentCodes = [
    240 => [],
    400 => []
  ];

  /**
   * A instrução do boleto, ou seja, o que deve ser feito em relação ao
   * mesmo junto à instituição bancária (se o mesmo deve ser registrado,
   * alterado, dado baixa ou modificada a sua data no banco, por
   * exemplo).
   *
   * @var int
   */
  protected $billetInstruction = BilletInstruction::REGISTRATION;

  /**
   * Um estado customizado para o boleto.
   * 
   * @var int
   */
  private $customStatus = null;


  // -----[ Informações calculadas ]------------------------------------

  /**
   * Cache do número de identificação do título no banco (nosso número)
   * para evitar processamento desnecessário.
   *
   * @var string
   */
  protected $bankIdentificationNumber;

  /**
   * Cache do código de barras para evitar processamento desnecessário.
   *
   * @var string
   */
  protected $barCode;

  /**
   * Cache da linha digitável para evitar processamento desnecessário.
   *
   * @var string
   */
  protected $digitableLine;


  /**
   * O construtor de nosso gerador de boletos.
   *
   * @param array $params
   *   Os parâmetros do boleto
   */
  public function __construct(array $params = [])
  {
    // Percorre os parâmetros, fazendo as chamadas aos métodos para
    // inicializar este boleto
    foreach ($params as $param => $value) {
      // Convertemos o nome do parâmetro para o padrão interno
      $param = str_replace(' ', '', ucwords(str_replace('_', ' ', $param)));

      // Não permitimos a configuração automática de campos protegidos
      if (in_array(lcfirst($param), $this->protectedFields)) {
        continue;
      }

      // Verifica se o método existe
      if (method_exists($this, 'set' . $param)) {
        // Faz a chamada ao método, passando o valor como parâmetro
        $this->{'set' . $param}($value);
      }
    }
    
    // Marca as datas internas para seus respectivos padrões caso as
    // mesmas ainda não tenham sido especificadas
    if (!$this->dateOfDocument) {
      $this->setDateOfDocument(Carbon::now());
    }
    if (!$this->dateOfProcessing) {
      $this->setDateOfProcessing(Carbon::now());
    }
    if (!$this->dateOfExpiration) {
      $this->setDateOfExpiration(Carbon::now()->addDays(5));
    }
    if (!$this->dateOfDiscount) {
      $this->setDateOfDiscount(Carbon::now()->addDays(5));
    }
  }

  /**
   * Seta os campos obrigatórios.
   *
   * @param string $fields
   *   A relação de campos obrigatórios
   *
   * @return $this
   *   A instância do boleto
   */
  protected function setRequiredFields(string ...$fields): self
  {
    $this->requiredFields = [];
    foreach ($fields as $field) {
      if (!array_key_exists($field, $this->requiredFields)) {
        $this->requiredFields[] = $field;
      }
    }

    return $this;
  }

  /**
   * Método que determina se foram preenchidos todos os campos que são
   * obrigatórios para emissão do boleto.
   *
   * @param $messages
   *
   * @return boolean
   */
  public function isValid(&$messages)
  {
    foreach ($this->requiredFields AS $field) {
        $test = call_user_func([$this, 'get' . lcfirst($field)]);
        if ($test === '' || is_null($test)) {
            $messages .= "O campo '{$field}' não foi informado; ";
            return false;
        }
    }
    return true;
  }


  // =====[ Banco Emissor]==============================================

  /**
   * Obtém o código do banco emissor.
   *
   * @return string
   */
  public function getBankCode(): string
  {
    return $this->bankCode;
  }

  /**
   * Obtém o código do banco formatado.
   *
   * @return string
   */
  public function getFormattedBankCode(): string
  {
    return $this->zeroFill($this->bankCode, 3);
  }
  
  /**
   * Obtém o código do banco com o dígito verificador.
   *
   * @return string
   */
  public function getBankCodeWithDAC(): string
  {
    $bankCode = $this->getFormattedBankCode();
    $DAC = $this->checkSumMod11($bankCode, 9, '0', '0');
    
    return $bankCode . '-' . $DAC;
  }
  
  /**
   * Obtém o nome do arquivo contendo a logomarca do banco.
   *
   * @return string
   */
  public function getBankLogo(): string
  {
    return $this->bankLogoImage;
  }
  
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
  public function getComplementaryData(): array
  {
    return [];
  }


  // =====[ Informações do beneficiário (emissor) ]=====================

  /**
   * Define o beneficiário (o emissor).
   *
   * @param FinancialAgent $emitterEntity
   *   A entidade emissora do título
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setEmitter(
    FinancialAgent $emitterEntity
  ): BankingBillet
  {
    $this->emitterEntity = $emitterEntity;
    
    return $this;
  }
  
  /**
   * Obtém o beneficiário (o emissor).
   *
   * @return FinancialAgent
   */
  public function getEmitter(): ?FinancialAgent
  {
    return $this->emitterEntity;
  }

  /**
   * Obtém o número da agência formatado conforme os padrões do banco.
   *
   * @return string
   */
  abstract public function getAgencyNumber(): string;
  
  /**
   * Obtém o número da conta formatado conforme os padrões do banco.
   *
   * @return string
   */
  abstract public function getAccountNumber(): string;
  
  /**
   * Obtém a agência do beneficiário  do boleto.
   *
   * @return string
   */
  public function getAgencyNumberAndEmitterCode(): string
  {
    return $this->getAgencyNumber() . ' / ' . $this->getAccountNumber();
  }


  // =====[ Informações do pagante ]====================================

  /**
   * Define o pagador.
   *
   * @param FinancialAgent $payerEntity
   *   Os dados do pagador
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setPayer(FinancialAgent $payerEntity): BankingBillet
  {
    $this->payerEntity = $payerEntity;
    
    return $this;
  }
  
  /**
   * Obtém os dados do pagador.
   *
   * @return FinancialAgent
   */
  public function getPayer(): ?FinancialAgent
  {
    return $this->payerEntity;
  }


  // =====[ Informações do Avalista ]===================================
  
  /**
   * Define os dados da entidade sacador/avalista do boleto.
   *
   * @param FinancialAgent $guarantorEntity
   *   Os dados da entidade sacador/avalista
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setGuarantor(
    FinancialAgent $guarantorEntity
  ): BankingBillet
  {
    $this->guarantorEntity = $guarantorEntity;
    
    return $this;
  }
  
  /**
   * Obtém os dados da entidade sacador/avalista.
   *
   * @return FinancialAgent
   */
  public function getGuarantor(): ?FinancialAgent
  {
    return $this->guarantorEntity;
  }


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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setWallet(string $wallet): BankingBillet
  {
    if (!in_array($wallet, $this->getWallets())) {
      throw new InvalidArgumentException("Código de carteira não "
        . "disponível para este banco"
      );
    }
    
    $this->wallet = $wallet;
    
    return $this;
  }
  
  /**
   * Obtém o código da carteira (com ou sem registro).
   *
   * @return string
   */
  public function getWallet(): ?string
  {
    return $this->wallet;
  }
  
  /**
   * Obtém as carteiras disponíveis para este banco.
   *
   * @return array
   */
  public function getWallets(): array
  {
    return $this->wallets;
  }
  
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
  public function getWalletName(): string
  {
    return isset($this->walletsNames[$this->wallet])
      ? $this->walletsNames[$this->wallet]
      : $this->wallet
    ;
  }

  /**
   * Define a numeração do título, que é um número sequencial que é
   * incrementado a medida que os mesmos forem sendo ingressados no
   * sistema, e que não pode se repetir.
   * 
   * @param int $sequentialNumber
   *   Número sequencial
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setSequentialNumber(
    int $sequentialNumber
  ): BankingBillet
  {
    $this->sequentialNumber = $sequentialNumber;
    
    return $this;
  }
  
  /**
   * Obtém a numeração do título, que é um número sequencial que é
   * incrementado a medida que os mesmos forem sendo ingressados no
   * sistema, e que não pode se repetir.
   *
   * @return int
   */
  public function getSequentialNumber(): ?int
  {
    return $this->sequentialNumber;
  }


  // =====[ Informações do documento ]==================================

  /**
   * Define a data do documento. É a data em que o documento foi gerado
   * e cujo valor está sendo cobrado por este boleto.
   *
   * @param Carbon $dateOfDocument
   *   A data do documento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDateOfDocument(
    Carbon $dateOfDocument
  ): BankingBillet
  {
    $this->dateOfDocument = $dateOfDocument;
    
    return $this;
  }
  
  /**
   * Obtém a data do documento.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfDocument()
  {
    return $this->dateOfDocument;
  }

  /**
   * Define a espécie de documento, geralmente DM (Duplicata Mercantil).
   *
   * @param string $kindOfDocument
   *   A espécie de documento
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setKindOfDocument(string $kindOfDocument): self
  {
    $this->kindOfDocument = $kindOfDocument;
    
    return $this;
  }
  
  /**
   * Obtém a espécie de documento.
   *
   * @return string
   */
  public function getKindOfDocument(): string
  {
    return $this->kindOfDocument;
  }
  
  /**
   * Obtém o nome da espécie de documento.
   *
   * @throws InvalidArgumentException
   *   Em caso da espécie de documento não ter sido definida
   *
   * @return string
   */
  public function getKindOfDocumentName(): string
  {
    if (array_key_exists($this->kindOfDocument, $this->kindsOfDocuments)) {
      return $this->kindsOfDocuments[$this->kindOfDocument];
    }

    throw new InvalidArgumentException("A espécie de documento é "
      . "inválida ou não foi definida"
    );
  }

  /**
   * Define o número do documento.
   *
   * @param string $documentNumber
   *   O número do documento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDocumentNumber(
    $documentNumber
  ): BankingBillet
  {
    $this->documentNumber = $documentNumber;
    
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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setAcceptance(
    string $acceptance
  ): BankingBillet
  {
    if (array_key_exists(strtoupper($acceptance), [ 'A', 'N' ])) {
      $this->acceptance = strtoupper($acceptance);
    } else {
      throw new InvalidArgumentException("O aceite deve ser 'A' para "
        . "aceito e 'N' para não aceito"
      );
    }
    
    return $this;
  }
  
  /**
   * Obtém a informação de aceite ('A' para 'Aceito' e 'N' para 'Não').
   *
   * @return string
   */
  public function getAcceptance(): string
  {
    return $this->acceptance;
  }

  /**
   * Define a informação do código do tipo de moeda que o documento foi
   * emitido (R$, US$, IGPM etc.). O padrão é o Real (R$).
   *
   * @param int $currency
   *   O código do tipo de moeda
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setCurrency(int $currency): BankingBillet
  {
    $this->currency = $currency;
    
    return $this;
  }
  
  /**
   * Obtém a informação do código do tipo de moeda que o documento foi
   * emitido (R$, US$, IGPM etc.).
   *
   * @return int
   */
  public function getCurrency(): int
  {
    return $this->currency;
  }

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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setAmount(float $amount): BankingBillet
  {
    $this->amount = $amount;
    
    return $this;
  }
  
  /**
   * Obtém a quantidade a correspondente da moeda quando o documento é
   * emitido em moeda indexada (US$, IGPM etc.).
   *
   * @return float
   */
  public function getAmount(): float
  {
    return $this->amount;
  }

  /**
   * Define o valor do documento. O valor do documento deve ser
   * informado sempre que a moeda for o REAL e o boleto não for
   * contra-apresentação.
   *
   * @param float $documentValue
   *   O valor do documento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDocumentValue(
    float $documentValue
  ): BankingBillet
  {
    $this->documentValue = $documentValue;
    
    return $this;
  }

  /**
   * Obtém o valor do documento. O valor do documento sempre é em branco
   * quando o boleto for contra-apresentação. Normalmente também é
   * deixado em branco quando o boleto estiver em moeda estrangeira e o
   * valor da cotação da moeda (valor unitário) não tiver sido informado
   * antes. Caso contrário, utiliza o valor informado.
   *
   * @return float
   */
  public function getDocumentValue(): float
  {
    if ($this->againstPresentation) {
      // Se o boleto for contra-apresentação, o valor é sempre zero
      return 0.00;
    }

    if ($this->currency !== Coins::CURRENCY_BRAZILIAN_REAL) {
      // Se o boleto é em outra moeda, sempre retorna zero, pois o valor
      // é calculado no momento do pagamento em função da cotação no dia
      return 0.00;
    }

    // Retornamos o valor do documento informado
    return $this->documentValue;
  }
  
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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDemonstrativeText(
    $demonstrativeText
  ): BankingBillet
  {
    try {
      $demonstrativeText =
        $this->limitTextWithNLines($demonstrativeText, 5)
      ;
    } catch (InvalidArgumentException $e) {
      throw new InvalidArgumentException("A descrição do demonstrativo "
        . "não é válida. " . $e->getMessage()
      );
    }
    
    // Armazenamos a descrição
    $this->demonstrativeText = $demonstrativeText;
    
    return $this;
  }
  
  /**
   * Obtém uma matriz com a descrição do demonstrativo.
   *
   * @return string[]
   *   A descrição do demonstrativo com no máximo 5 linhas
   */
  public function getDemonstrativeText(): array
  {
    return $this->demonstrativeText;
  }

  /**
   * Define o número da parcela.
   *
   * @param int $parcel
   *   O número da parcela
   * @param int $total
   *   A quantidade de parcelas
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setParcel(
    int $parcel,
    int $total
  ): BankingBillet
  {
    // Armazenamos a descrição
    $this->parcel = $parcel;
    $this->numberOfParcels = $total;
    
    return $this;
  }

  /**
   * Obtém a informação de parcelamento.
   *
   * @return string
   *   A informação da parcela
   */
  public function getParcel(): string
  {
    return ($this->parcel)
      ? "{$this->parcel} / $this->numberOfParcels"
      : '';
  }

  /**
   * Define o mês de referência do qual está sendo cobrado neste
   * documento.
   *
   * @param string $referenceMonthYear
   *   O mês e ano de referência
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setReferenceMonth(
    string $referenceMonthYear = null
  ): BankingBillet
  {
    if ($referenceMonthYear == null) {
      $referenceMonthYear = '';
    }
    
    // Armazenamos a descrição
    $this->referenceMonthYear = $referenceMonthYear;
    
    return $this;
  }

  /**
   * Obtém o mês de referência do qual está sendo cobrado neste
   * documento.
   *
   * @return string
   *   O mês de referência
   */
  public function getReferenceMonth(): string
  {
    return ($this->referenceMonthYear)
      ? $this->referenceMonthYear
      : '';
  }

  /**
   * Define o totalizador de itens.
   *
   * @param int $total
   *   A quantidade de itens
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setTotalizer(int $total): BankingBillet
  {
    // Armazenamos a descrição
    $this->totalizer = $total;
    
    return $this;
  }

  /**
   * Obtém o totalizador de itens que estão sendo cobrado neste
   * documento.
   *
   * @return int
   *   A quantidade de itens
   */
  public function getTotalizer(): int
  {
    return $this->totalizer;
  }

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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setHistoric($historicText): BankingBillet
  {
    try {
      $historicText =
        $this->limitTextWithNLines($historicText, 12)
      ;
    } catch (InvalidArgumentException $e) {
      throw new InvalidArgumentException("A descrição do histórico de "
          . "operações não é válida. " . $e->getMessage()
      );
    }
    
    // Armazenamos a descrição
    $this->historic = $historicText;
    
    return $this;
  }
  
  /**
   * Obtém uma matriz com o histórico de operações.
   *
   * @return string[]
   *   O histórico de operações com no máximo 8 linhas
   */
  public function getHistoric(): array
  {
    return $this->historic;
  }


  // =====[ Informações da cobrança ]===================================

  /**
   * Define a data de geração do boleto (data de processamento).
   *
   * @param Carbon $dateOfProcessing
   *   A data de geração do boleto
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDateOfProcessing(
    Carbon $dateOfProcessing
  ): BankingBillet
  {
    $this->dateOfProcessing = $dateOfProcessing;
    
    return $this;
  }
  
  /**
   * Obtém a data de geração do boleto (Data de processamento).
   *
   * @return Carbon\Carbon
   */
  public function getDateOfProcessing()
  {
    return $this->dateOfProcessing;
  }

  /**
   * Define se o boleto é contra-apresentação, ou seja, a data de
   * vencimento e o valor são deixados em branco. Neste caso, sugere-se
   * que defina-se um valor para o pagamento mínimo.
   *
   * @param bool $againstPresentation
   *   A informação se é ou não contra-apresentação
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setAgainstPresentation(
    bool $againstPresentation
  ): BankingBillet
  {
    $this->againstPresentation = $againstPresentation;
    
    return $this;
  }
  
  /**
   * Obtém se o boleto é contra-apresentação, ou seja, a data de
   * vencimento é indefinida.
   *
   * @return bool
   */
  public function getAgainstPresentation(): bool
  {
    return $this->againstPresentation;
  }

  /**
   * Define valor para pagamento mínimo em boletos de contra
   * apresentação. Isto é necessário pois, quando a flag
   * contra-apresentação é definida, o valor do boleto é suprimido.
   *
   * @param float $minimumPayment
   *   O valor para pagamento mínimo
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setMinimumPayment(
    float $minimumPayment
  ): BankingBillet
  {
    $this->minimumPayment = $minimumPayment;

    // Força a definição de "contra-apresentação" do boleto
    $this->setAgainstPresentation(true);
    
    return $this;
  }
  
  /**
   * Obtém o valor para pagamento mínimo em boletos de contra
   * apresentação.
   *
   * @return float
   */
  public function getMinimumPayment(): float
  {
    return $this->minimumPayment;
  }

  /**
   * Define a data de vencimento do boleto.
   *
   * @param Carbon $dateOfExpiration
   *   A data de vencimento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDateOfExpiration(
    Carbon $dateOfExpiration
  ): BankingBillet
  {
    $this->dateOfExpiration = $dateOfExpiration;
    
    return $this;
  }

  /**
   * Obtém a data de vencimento do boleto.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfExpiration()
  {
    return $this->dateOfExpiration;
  }

  /**
   * Define a data limite para concessão de desconto no valor do
   * documento.
   *
   * @param Carbon $dateOfDiscount
   *   A data do documento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setDateOfDiscount(
    Carbon $dateOfDiscount
  ): BankingBillet
  {
    $this->dateOfDiscount = $dateOfDiscount;
    
    return $this;
  }
  
  /**
   * Obtém a data limite para concessão de desconto no valor do
   * documento.
   *
   * @return Carbon\Carbon
   */
  public function getDateOfDiscount()
  {
    return $this->dateOfDiscount;
  }

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
  ): BankingBillet
  {
    $this->discountValue = $discount;
    
    return $this;
  }
  
  /**
   * Obtém o valor do desconto.
   *
   * @return float
   */
  public function getDiscountValue(): float
  {
    return $this->discountValue;
  }
  
  /**
   * Define a quantidade de dias após o vencimento em que se inicia a
   * cobrança de juros.
   *
   * @param int $days
   *   A quantidade de dias após o vencimento em que se inicia a
   *   cobrança de juros
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setStartChargingInterestAfter(
    int $days
  ): BankingBillet
  {
    $this->startChargingInterestAfter = $days;
    
    return $this;
  }
  
  /**
   * Obtém a quantidade de dias após o vencimento em que se inicia a
   * cobrança de juros.
   *
   * @return int
   */
  public function getStartChargingInterestAfter(): int
  {
    return $this->startChargingInterestAfter;
  }

  /**
   * Retorna a data de início da incidência de juros sobre o valor do
   * documento.
   *
   * @return \Carbon\Carbon
   */
  public function getStartDateOfChargingInterest()
  {
    return $this->dateOfExpiration
      ->copy()
      ->addDays((int) $this->startChargingInterestAfter)
    ;
  }
  
  /**
   * Define o tipo do valor dos juros de mora.
   *
   * @param int $arrearInterestType
   *   O tipo do valor dos juros de mora
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setArrearInterestType(
    int $arrearInterestType
  ): BankingBillet
  {
    switch ($arrearInterestType) {
      case 1:
        // É um valor fixo
        $this->arrearInterestType = 1;
        break;
      
      default:
        // É um valor em percentagem
        $this->arrearInterestType = 2;
        break;
    }
    
    return $this;
  }
  
  /**
   * Obtém o tipo do valor dos juros de mora.
   *
   * @return int
   */
  public function getArrearInterestType(): int
  {
    return $this->arrearInterestType;
  }
  
  /**
   * Define o valor dos juros de mora por dia de atraso.
   *
   * @param float $arrearInterestPerDay
   *   O valor dos juros de mora a serem aplicados por dia de atraso
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setArrearInterestPerDay(
    float $arrearInterestPerDay
  ): BankingBillet
  {
    $this->arrearInterestPerDay = $arrearInterestPerDay;
    
    return $this;
  }
  
  /**
   * Obtém o valor dos juros de mora por dia de atraso a serem
   * aplicados.
   *
   * @return float
   */
  public function getArrearInterestPerDay(): float
  {
    if ($this->arrearInterestPerDay <= 0.00) {
      return 0.00;
    }

    return $this->arrearInterestPerDay;
  }
  
  /**
   * Define o valor da multa.
   *
   * @param float $fineValue
   *   O valor dos juros de mora e/ou multa a serem aplicados
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setFineValue(
    float $fineValue
  ): BankingBillet
  {
    $this->fineValue = $fineValue;
    
    return $this;
  }
  
  /**
   * Obtém o valor da multa.
   *
   * @return float
   */
  public function getFineValue(): float
  {
    return $this->fineValue;
  }
  
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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setInstructionAfterExpiration(
    int $instruction,
    int $days
  ): BankingBillet
  {
    if (Instructions::isValid($instruction)) {
      $this->instructionAfterExpiration = $instruction;

      $requireDays = [
        Instructions::PROTEST,
        Instructions::BANKRUPTCY_PROTEST,
        Instructions::NEGATIVATE,
        Instructions::DROP_BECAUSE_EXPIRY_OF_TERM
      ];

      if (in_array($instruction, $requireDays)) {
        if ($days < 5) {
          throw new InvalidArgumentException("A quantidade de dias não "
            . "deve ser inferior a 5 dias"
          );
        }

        if ($days > 99) {
          throw new InvalidArgumentException("A quantidade de dias não "
            . "deve ser superior a 99 dias"
          );
        }

        $this->instructionDays = $days;
      } else {
        $this->instructionDays = 0;
      }
    } else {
      throw new InvalidArgumentException("A instrução informada é "
        . "inválida"
      );
    }
    
    return $this;
  }
  
  /**
   * Obtém a instrução para pré-determinar o protesto/negativação do
   * título e/ou a baixa por decurso de prazo, quando do registro.
   *
   * @return int
   */
  public function getInstructionAfterExpiration(): int
  {
    return $this->instructionAfterExpiration;
  }
  
  /**
   * Obtém a quantidade de dias após o vencimento em que a instrução
   * deve ocorrer.
   *
   * @return int
   */
  public function getInstructionDays(): int
  {
    return $this->instructionDays;
  }

  /**
   * Define a informação de uso exclusivo do banco. Neste campo é
   * impressa informação cujo conteúdo varia de instituição para
   * instituição.
   *
   * @param string $forBankUse
   *   O valor a ser incluído no campo "uso do banco"
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setForBankUse(
    string $forBankUse
  ): BankingBillet
  {
    $this->forBankUse = $forBankUse;
    
    return $this;
  }
  
  /**
   * Obtém a informação de uso exclusivo do banco.
   *
   * @return string
   */
  public function getForBankUse(): ?string
  {
    return $this->forBankUse;
  }

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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setInstructionsText(
    $instructionsText
  ): BankingBillet
  {
    try {
      $instructionsText =
        $this->limitTextWithNLines($instructionsText, 8)
      ;
    } catch (InvalidArgumentException $exception) {
      throw new InvalidArgumentException("As instruções para pagamento "
        . "não são válidas. " . $exception->getMessage()
      );
    }
    
    // Armazenamos as instruções
    $this->instructionsText = $instructionsText;
    
    return $this;
  }
  
  /**
   * Define uma matriz com as instruções para pagamento automaticamente
   * com base nos valores informados.
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setAutoInstructionsText(): BankingBillet
  {
    $this->instructionsText = [];

    if ($this->getDiscountValue() > 0.00) {
      $this->instructionsText[] = 'Conceder desconto de R$ {discountValue} para pagamento até {dateOfDiscount}';
    }

    if ($this->getFineValue() > 0.00) {
      if (count($this->instructionsText) > 0) {
        // Adiciona uma linha de separação
        $this->instructionsText[] = '';
      }

      if ($this->getArrearInterestPerDay() > 0.00) {
        if ($this->getArrearInterestType() == 1) {
          // Os juros de mora são um valor fixo
          $this->instructionsText[] = 'Após o vencimento cobrar {fineValue}% de multa e R$ {arrearInterestPerDay} de juros de mora ao dia.';
        } else {
          // Os juros de mora são uma percentagem do valor do título
          $this->instructionsText[] = 'Após o vencimento cobrar {fineValue}% de multa e {arrearInterestPerDay}% de juros de mora ao dia.';
        }
      } else {
        $this->instructionsText[] = 'Após o vencimento cobrar {fineValue}% de multa.';
      }
    } else {
      if (count($this->instructionsText) > 0) {
        // Adiciona uma linha de separação
        $this->instructionsText[] = '';
      }
      if ($this->getArrearInterestPerDay() > 0.00) {
        if ($this->getArrearInterestType() == 1) {
          $this->instructionsText[] = 'Após o vencimento cobrar R$ {arrearInterestPerDay} de juros de mora ao dia.';
        } else {
          $this->instructionsText[] = 'Após o vencimento cobrar {arrearInterestPerDay}% de juros de mora ao dia.';
        }
      }
    }

    switch ($this->getInstructionAfterExpiration()) {
      case Instructions::NOT_RECEIVE_AFTER_EXPIRATION:
        if (count($this->instructionsText) > 0) {
          // Adiciona uma linha de separação
          $this->instructionsText[] = '';
        }
        $this->instructionsText[] = 'Não receber após o vencimento.';

        break;
      case Instructions::NEGATIVATE:
        if (count($this->instructionsText) > 0) {
          // Adiciona uma linha de separação
          $this->instructionsText[] = '';
        }
        $this->instructionsText[] = 'Negativar após {instructionDays} dias.';

        break;
      case Instructions::PROTEST:
        if (count($this->instructionsText) > 0) {
          // Adiciona uma linha de separação
          $this->instructionsText[] = '';
        }
        $this->instructionsText[] = 'Protestar após {instructionDays} dias.';

        break;
      default:
        break;
    }

    // Completa a matriz com linhas adicionais se a mesma contiver menos
    // do que a quantidade de linhas necessárias
    while (count($this->instructionsText) < 8) {
      $this->instructionsText[] = '';
    }

    return $this;
  }
  
  /**
   * Obtém uma matriz com as instruções para pagamento.
   *
   * @return string[]
   *   As instruções para pagamento com no máximo 8 linhas
   */
  public function getInstructionsText(): array
  {
    $instructions = [];
    $params = [
      'discountValue' => $this->moneyFormat($this->getDiscountValue(), true),
      'dateOfDiscount' => $this->getDateOfDiscount()->format('d/m/Y'),
      'fineValue' => $this->percentFormat($this->getFineValue(), 2, false),
      'arrearInterestPerDay' => ($this->getArrearInterestType() == 1)
        ? $this->percentFormat($this->getArrearInterestPerDay(), 2)
        : $this->percentFormat($this->getArrearInterestPerDay(), 4),
      'instructionDays' => $this->getInstructionDays()
    ];

    // Interpolamos os valores das instruções com os parâmetros
    foreach ($this->instructionsText AS $instruction) {
      // Interpola os valores dos parâmetros na instrução
      $instructions[] = $this->interpolate($instruction, $params);
    }

    return $instructions;
  }

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
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setPrintInstructionsText(
    $instructionsText
  ): BankingBillet
  {
    try {
      $instructionsText =
        $this->limitTextWithNLines($instructionsText, 5)
      ;
    } catch (InvalidArgumentException $exception) {
      throw new InvalidArgumentException("As instruções para pagamento "
        . "não são válidas. " . $exception->getMessage()
      );
    }
    
    // Armazenamos as instruções
    $this->printInstructionsText = $instructionsText;
    
    return $this;
  }
  
  /**
   * Obtém uma matriz com as instruções para impressão.
   *
   * @return string[]
   *   As instruções para impressão com no máximo 5 linhas
   */
  public function getPrintInstructionsText(): array
  {
    return $this->printInstructionsText;
  }

  /**
   * Define o local de pagamento do boleto.
   *
   * @param string $paymentPlace
   *   O local de pagamento
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setPaymentPlace(
    string $paymentPlace
  ): BankingBillet
  {
    $this->paymentPlace = $paymentPlace;
    
    return $this;
  }
  
  /**
   * Obtém o local de pagamento do boleto.
   *
   * @return string
   */
  public function getPaymentPlace(): string
  {
    return $this->paymentPlace;
  }


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
    int $cnabType = 240): string
  {
    if (!array_key_exists($cnabType, $this->kindOfDocumentCodes)) {
      throw new InvalidArgumentException("O CNAB {$cnabType} não está "
        . "disponível para este banco"
      );
    }

    $codes = $this->kindOfDocumentCodes[$cnabType];

    return array_key_exists(strtoupper($this->kindOfDocument), $codes)
      ? $codes[strtoupper($this->kindOfDocument)]
      : $default
    ;
  }

  /**
   * Define o número de controle interno (da aplicação) para controle da
   * remessa.
   *
   * @param string $controlNumber
   *   O número de controle interno
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setControlNumber(
    $controlNumber
  ): BankingBillet
  {
    $this->controlNumber = $controlNumber;
    
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
    return $this->controlNumber?:'0';
  }
  
  /**
   * Define que o documento (boleto) deve ser registrado.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setToRegistration(): self
  {
    $this->billetInstruction = BilletInstruction::REGISTRATION;
    
    return $this;
  }

  /**
   * Define que o documento (boleto) deve ser alterado. As modificações
   * estão nos valores deste boleto.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setToModification(): self
  {
    $this->billetInstruction = BilletInstruction::MODIFICATION;
    
    return $this;
  }
  
  /**
   * Define que o documento (boleto) deve ter sua data de vencimento
   * modificada. As modificações estão nos valores deste boleto.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setToChangeDate(): self
  {
    $this->billetInstruction = BilletInstruction::DATE_CHANGE;
    
    return $this;
  }
  
  /**
   * Define que o documento (boleto) deve ser baixado.
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setToDischarge(): self
  {
    $this->billetInstruction = BilletInstruction::DISCHARGE;
    
    return $this;
  }

  /**
   * Define a instrução a ser realizada em relação ao boleto.
   * 
   * @param int $billetInstruction
   *   A instrução a ser executada em relação ao boleto
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setBilletInstruction(
    int $billetInstruction
  ): BankingBillet
  {
    $this->billetInstruction = $billetInstruction;
    
    return $this;
  }

  /**
   * Obtém a instrução a ser realizada em relação ao boleto.
   *
   * @return int
   */
  public function getBilletInstruction(): int
  {
    return $this->billetInstruction;
  }
  
  /**
   * Obtém a descrição da instrução a ser aplicada no boleto.
   *
   * @return string
   */
  public function getBilletInstructionName(): string
  {
    return BilletInstruction::toString($this->billetInstruction);
  }
  
  /**
   * Define o envio de uma instrução personalizada para o boleto.
   * 
   * @param int $instructionCode
   *   O código da instrução personalizada
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setCustomInstruction(
    int $instructionCode
  ): BankingBillet
  {
    $this->billetInstruction = BilletInstruction::CUSTOM;
    $this->customStatus = $instructionCode;
    
    return $this;
  }
  
  /**
   * Define parâmetros adicionais específicos de um banco necessários
   * para geração do boleto.
   * 
   * @param array $params
   *   Os parâmetros adicionais
   * 
   * @return BankingBillet
   *   A instância do boleto
   */
  public function setAdditionalParameters(
    array $params = []
  ): BankingBillet
  {
    // Obtemos os métodos existentes e criamos uma matriz com os métodos
    // case insensitive
    $methods = get_class_methods($this);
    $realMethods = [];
    foreach ($methods AS $method) {
      $realMethods[strtolower($method)] = $method;
    }

    // Percorre os parâmetros, fazendo as chamadas aos métodos para
    // inicializar este agente de maneira que as chaves sejam
    // "case insensitive"
    foreach ($params as $param => $value) {
      $param = strtolower($param);

      // Verifica se o método existe
      if (array_key_exists('set' . $param, $realMethods)) {
        // Faz a chamada ao método, passando o valor como parâmetro
        $this->{$realMethods['set' . $param]}($value);
      }
    }
    
    return $this;
  }

  /**
   * Obtém a instrução personalizada a ser aplicada no boleto.
   *
   * @return int|null
   */
  public function getCustomInstruction(): ?int
  {
    return ($this->billetInstruction === BilletInstruction::CUSTOM)
      ? $this->customStatus
      : NULL
    ;
  }

  /**
   * Gerar o código do campo livre para as posições de 20 a 44.
   *
   * @param string $freeField
   *
   * @return array
   */
   public abstract function parseFreeField(string $freeField): array;


  // =====[ Informações calculadas ]====================================

  /**
   * Define a identificação do título no banco (comumente denominado de
   * "nosso número"). O número do título é a principal chave de acesso
   * ao registro deste boleto no banco. Os títulos registrados recebem
   * uma numeração constituída de dígitos que modificam de instituição
   * para instituição. Este valor é gerado através da função
   * buildBankIdentificationNumber().
   * 
   * @throws RuntimeException
   *   Em caso de ocorrer uma chamada ao erroneamente tentar setar o
   *   "Nosso Número", já que o mesmo é calculado internamente em função
   *   dos valores fornecidos através da função buildBankIdentificationNumber()
   */
  public final function setBankIdentificationNumber(): void
  {
    throw new RuntimeException("Não é possível definir o número de "
      . "identificação do título no banco (nosso número) diretamente. "
      . "Utilize o método setSequentialNumber()."
    );
  }

  /**
   * Obtém o número de identificação do título no banco ("nosso número"),
   * baseado nas regras da instituição pela qual o boleto será emitido.
   *
   * @throws InvalidArgumentException
   *   Em caso de algum parâmetro inválido
   *
   * @return string
   */
  protected abstract function buildBankIdentificationNumber(): string;
  
  /**
   * Implementação da faixa livre do código de um boleto definido da
   * posição 20 à 44, com base nas regras da instituição pela qual o
   * boleto será emitido, conforme determinado pela FEBRABAN.
   *
   * @return string
   */
  public abstract function getFreeField(): string;
  
  /**
   * Obtém o número de identificação do título no banco ("nosso número").
   * O número do título é a principal chave de acesso ao registro deste
   * boleto no banco. Os títulos registrados recebem uma numeração
   * constituída de dígitos que modificam de instituição para
   * instituição. Este valor é gerado através da função buildBankIdentificationNumber().
   *
   * @return string
   */
  public function getBankIdentificationNumber(): string
  {
    if (empty($this->bankIdentificationNumber)) {
      $this->bankIdentificationNumber = $this->buildBankIdentificationNumber();
    }
    
    return $this->bankIdentificationNumber;
  }

  /**
   * Obtém o número de identificação do título no banco ("nosso número")
   * a ser utilizado no boleto. Algumas instituições possuem diferença
   * entre este valor e o que é usado na transmissão.
   *
   * @return string
   */
  public function getBankIdentificationNumberForBillet(): string
  {
    return $this->getBankIdentificationNumber();
  }

  /**
   * Obtém o dígito verificador do código de barras segundo o padrão
   * FEBRABAN.
   *
   * @return string
   */
  protected function getDAC(): string
  {
    $num = $this->zeroFill($this->bankCode, 4)
      . $this->getCurrency()
      . $this->getExpirationFactor()
      . $this->getValueFilledWithZeros()
      . $this->getFreeField()
    ;

    $DAC = $this->checkSumMod11($num, 9, '1', '1');
    
    return $DAC;
  }
  
  /**
   * Obtém o número do código de barras segundo o padrão FEBRABAN.
   *
   * @return string
   */
  public function getBarCodeNumber(): string
  {
    return ''
      . $this->getBankCode()
      . $this->getCurrency()
      . $this->getDAC()
      . $this->getExpirationFactor()
      . $this->getValueFilledWithZeros()
      . $this->getFreeField()
    ;
  }
  
  /**
   * Obtém a linha digitável do boleto.
   *
   * @return string
   */
  public function getDigitableLine(): string
  {
    // O código da posição de 20 a 44
    $freeField = $this->getFreeField();
    
    // Divide as posições do código Febraban de 20 a 44 em 3 blocos de
    // 5, 10 e 10 caracteres cada.
    $blocks = array(
      '20-24' => substr($freeField, 0, 5),
      '25-34' => substr($freeField, 5, 10),
      '35-44' => substr($freeField, 15, 10),
    );
    
    // Concatena o código do banco com o código da moeda e com o
    // primeiro bloco de 5 caracteres do campo livre e calcula o dígito
    // verificador
    $check_digit = $this->checkSumMod10(''
      . $this->zeroFill($this->bankCode, 3)
      . $this->getCurrency()
      . $blocks['20-24']
    );
    
    // Inclui um ponto no bloco 20-24 (5 caracteres) na sua 2ª posição
    $blocks['20-24'] = substr_replace($blocks['20-24'], '.', 1, 0);
    
    // Concatena o código do banco com o código da moeda e com o
    // primeiro bloco de 5 caracteres do campo livre e o respectivo
    // dígito verificador
    $part1 = ''
      . $this->zeroFill($this->bankCode, 3)
      . $this->getCurrency()
      . $blocks['20-24']
      . $check_digit
    ;
    
    // Calcula o dígito verificador do 2º bloco do campo livre com 10
    // caracteres (parte2)
    $check_digit = $this->checkSumMod10($blocks['25-34']);
    
    // Concatena o 2º bloco do campo livre com 10 caracteres e o seu
    // respectivo dígito verificador (parte2)
    $part2 = $blocks['25-34'] . $check_digit;
    
    // Inclui um ponto na sua 6ª posição (parte2)
    $part2 = substr_replace($part2, '.', 5, 0);
    
    // Calcula o dígito verificador do 3º bloco do campo livre com 10
    // caracteres (parte3)
    $check_digit = $this->checkSumMod10($blocks['35-44']);
    
    // Concatena o 3º bloco do campo livre com 10 caracteres e o seu
    // respectivo dígito verificador (parte3)
    $part3 = $blocks['35-44'] . $check_digit;
    
    // Inclui um ponto na sua 6ª posição (parte3)
    $part3 = substr_replace($part3, '.', 5, 0);
    
    // Calcula o dígito verificador para a linha digitável do boleto
    $check_digit = $this->getDAC();

    // Concatena a 4ª parte
    $part4  = $this->getExpirationFactor()
      . $this->getValueFilledWithZeros()
    ;
    
    // Obtém os 4 blocos formatados
    return "$part1 $part2 $part3 $check_digit $part4";
  }


  
  /**
   * Obtém o valor do boleto com 10 dígitos e remoção de pontuações.
   *
   * @return string
   */
  protected function getValueFilledWithZeros(): string
  {
    return $this->zeroFill(
      number_format($this->getDocumentValue(), 2, '', ''),
      10
    );
  }
  

  // =====[ Funções auxiliares ]========================================
  
  /**
   * Calcula o fator de vencimento, expresso por meio de 4 dígitos, e
   * que é o número de dias entre a data base até a data de vencimento
   * do boleto, ou 0000 caso não tenha data de vencimento (o boleto seja
   * contra-apresentação), e que é utilizado para identificar a data de
   * vencimento do título no código de barras.
   *
   * À partir de 22/02/2025, o fator de vencimento retornará para “1000”,
   * devendo ser adicionado “1” a cada dia subsequente a esse fator,
   * conforme exemplo abaixo:
   *   - 22/02/2025: 1000
   *   - 23/02/2025: 1001
   *   - 24/02/2025: 1002
   *
   * @return string
   */
  protected function getExpirationFactor(): string
  {
    if (!$this->againstPresentation) {
      $baseDate = Carbon::createFromFormat('Y-m-d H:i:s',
        self::DUE_ON_BASE_DATE)->locale('pt_BR')
      ;
      $newBaseDate = Carbon::createFromFormat('Y-m-d H:i:s',
        self::DUE_ON_NEW_BASE_DATE)->locale('pt_BR')
      ;

      // O fator de vencimento, expresso por meio de 4 dígitos, e que é
      // o número de dias entre a data base até a data de vencimento do
      // boleto, possui uma data em que ocorre o esgotamento deste
      // cálculo, no caso a data de 21/02/2025, o que corresponde ao
      // número '9999'. À partir do dia seguinte desta data, o cálculo
      // segue o mesmo princípio, porém retornando ao valor inicial 1000
      if ($this->dateOfExpiration->lessThan($newBaseDate)) {
        // Determina a diferença de dias entre a data base até a data de
        // vencimento do boleto
        $days = $baseDate->diff($this->dateOfExpiration)->days;
      } else {
        // Determina a diferença de dias entre a nova data base até a
        // data de vencimento do boleto e acrescenta 1000
        $days = 1000 + $newBaseDate->diff($this->dateOfExpiration)->days;
      }

      return $this->zeroFill($days, 4);
    } else {
      return '0000';
    }
  }
}
