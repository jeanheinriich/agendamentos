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
 * Uma classe abstrata para servir como base para geradores de arquivos
 * de remessa no padrão CNAB da FEBRABAN.
 *
 * O CNAB, sigla para Centro Nacional de Automação Bancária, são
 * diretrizes a serem seguidas para a emissão dos arquivos de remessa e
 * de retorno, tanto por parte das empresas quanto dos bancos.
 * 
 * Ele nada mais é, portanto, do que o layout para registro de cobranças
 * em um molde padrão, o que garante a segurança do pagamento.
 * 
 * Somente com a realização desse registro é possível ao emissor receber
 * o valor referente aos boletos pagos pelos seus clientes. Se um boleto
 * bancário não passar por esse processo, será rejeitado pelo banco.
 *
 * Esta classe permite abstrair a geração do arquivo que permite
 * realizar este registro junto ao banco.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Shipping\Cnab400;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\Cnab\BilletInstruction;
use Core\Payments\Cnab\Instructions;
use InvalidArgumentException;

class Bradesco
  extends AbstractShippingFile
{
  /**
   * Caracter de fim de linha.
   *
   * @var string
   */
  protected $EOL = "\r\n";

  /**
   * Caracter de fim de arquivo.
   *
   * @var null|string
   */
  protected $EOF = "\r\n";


  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 237;


  // -----[ Informações do contrato com o banco emissor ]---------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = [ '2', '4', '9', '21', '26', '28' ];

  /**
   * O cache do código do cliente junto ao banco.
   *
   * @var string
   */
  protected $emitterCode;


  public function __construct(array $params = [])
  {
    parent::__construct($params);
    $this->addRequiredField('emitterCode');
  }

  /**
   * Define o código do cliente.
   *
   * @param string $emitterCode
   *   O código do cliente
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  public function setEmitterCode(string $emitterCode): self
  {
    $this->emitterCode = $emitterCode;

    return $this;
  }

  /**
   * Obtém o código do cliente emissor.
   *
   * @return string
   *   O código do cliente
   */
  public function getEmitterCode(): string
  {
    // Geramos a informação do emissor, se ainda não foi gerada
    if (empty($this->emitterCode)) {
      $this->emitterCode = ''
        . $this->formatField('9', $this->getWalletNumber(), 4)
        . $this->formatField('9', $this->emitterEntity->getAgencyNumber(), 5)
        . $this->formatField('9', $this->emitterEntity->getAccountNumber(), 7)
        . $this->formatField('9', $this->emitterEntity->getDACOfAccountNumber(), 1);
    }

    return $this->emitterCode;
  }

  /**
   * Gera as informações do cabeçalho do arquivo.
   * 
   * @return $this
   *   A instância do gerador de arquivos de remessa
   * 
   * @throws InvalidArgumentException
   *   Em caso de algum dos argumentos sejam inválidos
   * @throws RangeException
   *   Em caso do campo ultrapassar os limites da linha
   */
  protected function header()
  {
    // Inicializa a linha de cabeçalho
    $this->initHeader();

    // Identificação do registro: no 'Header' é sempre '0'
    $this->add(1, 1, '0');

    // Identificação do tipo de arquivo
    //   '1' - Remessa
    $this->add(2, 2, '1');
    $this->add(3, 9, 'REMESSA');

    // Identificação do serviço
    //   '01' - 'COBRANCA'
    $this->add(10, 11, '01');
    $this->add(12, 26, $this->formatField('X', 'COBRANCA', 15));

    // Código de identificação da empresa
    $this->add(27, 46, $this->formatField('9', $this->getEmitterCode(), 20));

    // Nome da empresa emissora
    $emitterName = substr($this->getEmitter()->getName(), 0, 30);
    $this->add(47, 76, $this->formatField('X', $emitterName, 30));

    // Código do banco na câmera de compensação
    //   '237' - 'BRADESCO'
    $this->add(77, 79, $this->getBankCode());
    $this->add(80, 94, $this->formatField('X', 'Bradesco', 15));

    // Data da gravação do arquivo
    $this->add(95, 100, $this->getDateOfShipping()->format('dmy'));

    // Branco
    $this->add(101, 108, '');

    // Identificação do sistema
    $this->add(109, 110, 'MX');

    // Nº sequencial de remessa
    $this->add(111, 117, $this->formatField('9', $this->getSequentialShippingNumber(), 7));

    // Branco
    $this->add(118, 394, '');

    // Número sequencial do registro dentro do arquivo (para o cabeçalho
    // é sempre 1)
    $this->add(395, 400, $this->formatField('9', 1, 6));

    return $this;
  }

  /**
   * Adiciona um boleto a ser registrado.
   *
   * @param BankingBillet $billet
   *   O boleto a ser adicionado
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  public function addBillet(BankingBillet $billet): self
  {
    // Inicializa o registro de transação
    $this->initTransaction();

    // Registro de Transação - Tipo 1

    // Identificação do registro: nas 'Transações' é sempre '1'
    $this->add(1, 1, '1');

    // Agência de débito do pagante (opcional)
    $this->add(2, 6, '');

    // DAC da agência de débito do pagante (opcional)
    $this->add(7, 7, '');

    // Razão social do pagante (opcional)
    $this->add(8, 12, '');

    // Número da conta-corrente do pagante (opcional)
    $this->add(13, 19, '');

    // DAC do número da conta-corrente do pagante (opcional)
    $this->add(20, 20, '');

    // Identificação do beneficiário
    //   '0', Carteira, Agência e Conta-corrente
    $this->add(21, 21, '0');
    $this->add(22, 24, $this->formatField('9', $this->getWallet(), 3));
    $this->add(25, 29, $this->formatField('9', $this->emitterEntity->getAgencyNumber(), 5));
    $this->add(30, 36, $this->formatField('9', $this->emitterEntity->getAccountNumber(), 7));
    $this->add(37, 37, $this->formatField('9', $this->emitterEntity->getDACOfAccountNumber(), 1));

    // Nº de controle do emissor
    $this->add(38, 62, $this->formatField('X', $billet->getControlNumber(), 25));

    // Código do banco a ser debitado (para débito automático)
    $this->add(63, 65, '000');

    // Indicação de multa
    //   0 - Sem multa
    //   2 - Com multa
    $this->add(66, 66,
      $billet->getFineValue() > 0.00
        ? '2'
        : '0'
    );

    // Percentual da multa, com dois dígitos de casas decimais
    $this->add(67, 70,
      $this->formatField('9',
        ($billet->getFineValue() > 0
          ? $billet->getFineValue()
          : '0'),
        4, 2
      )
    );

    // Identificação do título no banco (Nosso número) com o DAC
    $this->add(71, 82,
      $this->formatField('9',
        $billet->getBankIdentificationNumber(), 12
      )
    );

    // Desconto/bonificação por dia
    $this->add(83, 92, $this->formatField('9', 0, 10, 2));


    // Informação de emissão do boleto
    //   1 = Banco emite e processa o registro;
    //   2 = Cliente emite o boleto e o banco somente processa o registro;
    $this->add(93, 93, '2');

    // Informação de registro em débito automático
    //   N = Não registra na cobrança;
    //   Diferente de N registra e emite Boleto;
    $this->add(94, 94, '');

    // Identificação da operação do banco: sempre brancos
    $this->add(95, 104, '');

    // Indicador de rateio de crédito (opcional)
    $this->add(105, 105, '');

    // Endereçamento para aviso do débito automático em conta-corrente
    // (opcional)
    //   1 = emite aviso, e assume o endereço do pagador constante do
    //       arquivo-remessa;
    //   2 = não emite aviso;
    $this->add(106, 106, '2');

    // Quantidade de pagamentos
    $this->add(107, 108, '');

    // Identificação da ocorrência
    switch ($billet->getBilletInstruction()) {
      case BilletInstruction::REGISTRATION:
        // O título deve ser registrado
        $this->add(109, 110, '01');

        break;
      case BilletInstruction::DISCHARGE:
        // O título deve ser baixado
        $this->add(109, 110, '02');

        break;
      case BilletInstruction::DISCOUNT:
        // Concessão de desconto/abatimento no título
        $this->add(109, 110, '04');

        break;
      case BilletInstruction::DISCOUNT_CANCEL:
        // Cancelamento do desconto/abatimento no título
        $this->add(109, 110, '05');

        break;
      case BilletInstruction::DATE_CHANGE:
        // O título deve ter sua data de vencimento modificada
        $this->add(109, 110, '06');

        break;
      case BilletInstruction::MODIFICATION:
        // O título deve ser modificado
        $this->add(109, 110, '31');

        break;
      case BilletInstruction::REQUEST_PROTEST:
        // O título deve ser protestado
        $this->add(109, 110, '09');

        break;
      case BilletInstruction::SUSPEND_PROTEST_AND_DISCHARGE:
        // O título deve ter o protesto suspenso e ser baixado
        $this->add(109, 110, '18');

        break;
      case BilletInstruction::SUSPEND_PROTEST_AND_REMAIN_PENDING:
        // O título deve ter o protesto suspenso e ser mantido pendente
        // em carteira
        $this->add(109, 110, '19');

        break;
      case BilletInstruction::REQUEST_CREDIT_BLOCKED:
        // O título deve ser negativado
        $this->add(109, 110, '45');

        break;
      case BilletInstruction::SUSPEND_CREDIT_BLOCKED_AND_DISCHARGE:
        // O título deve ter a negativação suspensa e ser baixado
        $this->add(109, 110, '46');

        break;
      case BilletInstruction::SUSPEND_CREDIT_BLOCKED_AND_REMAIN_PENDING:
        // O título deve ter a negativação suspensa e ser mantido
        // pendente em carteira
        $this->add(109, 110, '47');

        break;
      default:
        // Envio de uma instrução personalizada
        $this->add(109, 110, sprintf(
            '%2.02s', $billet->getCustomInstruction()
          )
        );

        break;
    }

    // Número do documento
    $this->add(111, 120,
      $this->formatField('X', $billet->getDocumentNumber(), 10)
    );

    // Data de vencimento
    $this->add(121, 126,
      $billet->getDateOfExpiration()->format('dmy')
    );

    // Valor do documento (valor do título)
    $this->add(127, 139,
      $this->formatField('9', $billet->getDocumentValue(), 13, 2)
    );

    // Banco encarregado da cobrança
    $this->add(140, 142, '000');

    // Agência depositária
    $this->add(143, 147, '00000');

    // Espécie de título
    $this->add(148, 149, $billet->getKindOfDocumentCode(99, 400));

    // Indentificação de aceite
    $this->add(150, 150, $billet->getAcceptance());

    // A data de emissão do documento
    $this->add(151, 156,
      $billet->getDateOfDocument()->format('dmy')
    );

    // Instruções para pré-determinar o protesto/negativação do título
    // e/ou a baixa por decurso de prazo, quando do registro
    switch ($billet->getInstructionAfterExpiration()) {
      case Instructions::BANKRUPTCY_PROTEST:
        // Protesto Falimentar após 'N' dias
        $this->add(157, 158, '05');
        
        // Indica quantos dias após o vencimento deve ocorrer o protesto
        $this->add(159, 160,
          $this->formatField('9', $billet->getInstructionDays(), 2)
        );

        break;
      case Instructions::PROTEST:
        // Protestar após 'N' dias
        $this->add(157, 158, '06');

        // Indica quantos dias após o vencimento deve ocorrer o protesto
        $this->add(159, 160,
          $this->formatField('9', $billet->getInstructionDays(), 2)
        );

        break;
      case Instructions::NEGATIVATE:
        // Negativar após 'N' dias
        $this->add(157, 158, '07');
        
        // Indica quantos dias após o vencimento deve ocorrer a
        // negativação
        $this->add(159, 160,
          $this->formatField('9', $billet->getInstructionDays(), 2)
        );

        break;
      case Instructions::NOT_CHARGE_FINE_VALUE:
        // Não cobrar juros de mora
        $this->add(157, 158, '08');
        $this->add(159, 160, '00');

        break;
      case Instructions::NOT_RECEIVE_AFTER_EXPIRATION:
        // Não receber após o vencimento
        $this->add(157, 158, '09');
        $this->add(159, 160, '00');

        break;
      case Instructions::DROP_BECAUSE_EXPIRY_OF_TERM:
        // Baixa por decurso de prazo
        $this->add(157, 158, '18');
        
        // Indica quantos dias após o vencimento deve ocorrer a baixa
        // por decurso de prazo
        $this->add(159, 160,
          $this->formatField('9', $billet->getInstructionDays(), 2)
        );

        break;
      case Instructions::CANCEL_PROTEST_OR_NEGATIVATION:
        // Cancelamento de protesto e/ou negativação
        if ($billet->getBilletInstruction() !== BilletInstruction::MODIFICATION) {
          // Somente podemos enviar esta instrução em caso de estar
          // ocorrendo a modificação do título
          throw new InvalidArgumentException("O cancelamento de "
            . "protesto somente pode ocorrer na modificação do título"
          );
        }

        $this->add(157, 158, '99');
        $this->add(159, 160, '99');

        break;
      default:
        // Não havendo instrução, preencher com zeros
        $this->add(157, 158, '00');
        $this->add(159, 160, '00');

        break;
    }

    // Informações de juros de mora por dia. Aqui calculamos o valor do
    // juros de mora em função do valor do título e dos juros informados
    if ($billet->getArrearInterestType() == 1) {
      // 1. Juros de mora é um valor fixo
      $this->add(161, 173,
        $this->formatField('9',
          $billet->getArrearInterestPerDay()
          , 13, 2
        )
      );
    } else {
      // 2. Juros de mora é uma percentagem
      $this->add(161, 173,
        $this->formatField('9',
          $this->percent(
            $billet->getDocumentValue(),
            $billet->getArrearInterestPerDay()
          ), 13, 2
        )
      );
    }

    // Data limite para concessão de desconto
    $this->add(174, 179,
      ($billet->getDiscountValue() > 0
        ? $billet->getDateOfDiscount()->format('dmy')
        : '000000')
    );

    // Valor do desconto
    $this->add(180, 192,
      $this->formatField('9', $billet->getDiscountValue(), 13, 2)
    );

    // Valor do IOF
    $this->add(193, 205, $this->formatField('9', 0, 13, 2));

    // Valor do abatimento
    $this->add(206, 218, $this->formatField('9', 0, 13, 2));

    $payer = $billet->getPayer();

    // Identificação do tipo de inscrição do pagador
    //   01 - CPF
    //   02 - CNPJ
    $this->add(219, 220,
      $payer->getDocumentType() === 'CPF'
        ? '01'
        : '02'
    );

    // Número do documento
    $documentNumber = $this->getOnlyNumbers($payer->getDocument());
    $this->add(221, 234, $this->formatField('9', $documentNumber, 14));
    
    // Nome do pagante
    $this->add(235, 274, $this->formatField('X', $payer->getName(), 40));

    // Endereço do pagante
    $payerAddress = '';
    $payerAddress .= trim($payer->getAddress());
    $payerAddress .= ($payer->getStreetNumber() !== null)
      ? ', ' . trim($payer->getStreetNumber())
      : ''
    ;
    $payerAddress .= ($payer->getComplement() !== null)
      ? ' - ' . $payer->getComplement()
      : ''
    ;
    $this->add(275, 314,
      $this->formatField('X', $payerAddress, 40)
    );

    // 1ª mensagem: utilizamos para colocar o bairro
    $this->add(315, 326,
      $this->formatField('X', $payer->getDistrict(), 12)
    );

    // CEP
    $this->add(327, 334,
      $this->formatField('9', $this->getOnlyNumbers($payer->getPostalCode()), 8)
    );

    // Beneficiário final ou 2ª mensagem
    $this->add(335, 394,
      $this->formatField('X',
        $billet->getGuarantor()
          ? $billet->getGuarantor()->getName()
          : ''
        , 60
      )
    );

    // Número sequencial do registro dentro do arquivo
    $this->add(395, 400,
      $this->formatField('9', $this->registerCount, 6)
    );

    return $this;
  }

  /**
   * Função que gera o fechamento do arquivo.
   *
   * @return $this
   *   A instância do gerador de arquivos de remessa
   */
  protected function trailer()
  {
    $this->initTrailer();

    // Identificação do registro: no 'Trailer' é sempre '9'
    $this->add(1, 1, '9');

    // Em branco
    $this->add(2, 394, '');

    // Número sequencial do registro dentro do arquivo
    $this->add(395, 400, $this->formatField('9', $this->registerCount, 6));

    return $this;
  }

   /**
   * Obtém o nome do arquivo de remessa.
   *
   * @param int $dayCount
   *   O contador de arquivos gerados no dia
   *
   * @return string
   *   O nome do arquivo
   */
  public function getFileName(int $dayCount): string
  {
    /**
     *
     * O nome do arquivo-remessa no Bradesco deverá ter a seguinte
     * formatação:
     *   CBDDMM??.REM, onde
     *     CB: Indicador de cobrança Bradesco;
     *     DD: o dia de geração do arquivo;
     *     MM: o mês da geração do arquivo;
     *     ??: o contador de arquivos gerados no dia (em base 36);
     *     .REM: a extensão do arquivo
     * 
     * Exemplos: CB010501.REM ou CB0105AB.REM ou CB0105A1.REM.
     */
    if ($dayCount > 1295) {
      throw new InvalidArgumentException("O limite de envio de "
        . "arquivos é de 1295 por dia"
      );
    }

    // Convertemos para base 36 (Números de 0 a 9 e letras de 'a' a 'z')
    $count = base_convert($dayCount, 10, 36);
    $count = substr(str_repeat(0, 2) . $count, - 2);

    $dayMonth = $this->getDateOfShipping()->format('dm');

    return strtoupper("CB{$dayMonth}{$count}.REM");
  }
}
