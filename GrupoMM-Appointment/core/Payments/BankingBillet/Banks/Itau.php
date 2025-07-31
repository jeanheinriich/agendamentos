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
 * Geração de boleto bancário baseado nas regras do Banco Itaú (341).
 *
 * Layout do código de barras:
 * 
 *   1   5   10   15   20   25   30   35   40  44
 *   │   │    │    │    │    │    │    │    │   │
 *   34196307200000666061750000005160148348516000
 *   34198307100000666061750000005190148348519000
 *   └┬┘↓↓└─┬┘└────┬───┘└┬┘└───┬──┘↓└─┬┘└─┬─┘↓└┬┘
 *    │ ││  │      │     │     │   │  │   │  │ └──╼ Zeros fixos
 *    │ ││  │      │     │     │   │  │   │  └────╼ DAC da agência e conta
 *    │ ││  │      │     │     │   │  │   └───────╼ Conta benefeciário
 *    │ ││  │      │     │     │   │  └───────────╼ Agência benefeciário
 *    │ ││  │      │     │     │   └──────────────╼ DAC (Agência/Conta/Carteira/Nosso Número)
 *    │ ││  │      │     │     └──────────────────╼ Nosso número
 *    │ ││  │      │     └────────────────────────╼ Carteira
 *    │ ││  │      └──────────────────────────────╼ Valor
 *    │ ││  └─────────────────────────────────────╼ Fator de vencimento
 *    │ │└────────────────────────────────────────╼ DAC do código de barras
 *    │ └─────────────────────────────────────────╼ Código da Moeda (Real "9", Outras "0")
 *    └───────────────────────────────────────────╼ Código do banco na câmara de compensação
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\AbstractBankingBillet;
use InvalidArgumentException;

class Itau
  extends AbstractBankingBillet
{
  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 341;
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'itau.png';


  // -----[ Informações do contrato com o banco emissor ]---------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = [
    '148', '149', '153', '108', '180', '121', '150', '109', '191',
    '116', '117', '119', '134', '135', '136', '104', '188', '147',
    '112', '115', '177', '172', '107', '204', '205', '206', '173',
    '196', '103', '102', '174', '198', '167', '202', '203', '175',
    '157'
  ];
  
  /**
   * O local de pagamento.
   *
   * @var string
   */
  protected $paymentPlace = "Até o vencimento, pague preferencialmente "
    . "no Itaú. Após o vencimento pague somente no Itaú"
  ;


  // -----[ Campos adicionais ]-----------------------------------------
  
  /**
   * Código do cliente.
   *
   * Campo obrigatório para emissão de boletos com carteira 198 fornecido
   * pelo banco com 5 dígitos
   *
   * @var string
   */
  protected $customerCode;
  
  /**
   * Dígito verificador da carteira/nosso número para impressão no
   * boleto.
   *
   * @var string
   */
  protected $DACOfWalletAndOurNumber;


  // =====[ Início dos campos do boleto ]===============================

  /**
   * Obtém o número da agência formatado conforme os padrões do banco.
   *
   * @return string
   */
  public function getAgencyNumber(): string
  {
    return $this->zeroFill($this->emitterEntity->getAgencyNumber(), 4);
  }

  /**
   * Obtém o número da conta formatado conforme os padrões do banco.
   *
   * @return string
   */
  public function getAccountNumber(): string
  {
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 5)
      . '-' . strval($this->emitterEntity->getDACOfAccountNumber())
    ;
  }
  
  /**
   * Define o código do cliente.
   *
   * @param string $customerCode
   *   O código do cliente
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setCustomerCode(string $customerCode): self
  {
    $this->customerCode = $customerCode;
    
    return $this;
  }
  
  /**
   * Obtém o código do cliente.
   *
   * @return string
   */
  public function getCustomerCode(): string
  {
    return $this->customerCode;
  }


  // =====[ Informações calculadas ]====================================

  /**
   * Obtém o número de identificação do título no banco ("nosso número"),
   * baseado nas regras da instituição pela qual o boleto será emitido.
   *
   * @return string
   *
   * @throws InvalidArgumentException
   *   Em caso de algum parâmetro inválido
   */
  protected function buildBankIdentificationNumber(): string
  {
    $this->getFreeField(); // Força o calculo do DV
    
    $ourNumber = $this->zeroFill($this->getWallet(), 3)
      . '/' . $this->zeroFill($this->sequentialNumber, 8)
    ;
    $ourNumber .= '-'
      . $this->DACOfWalletAndOurNumber
    ;
    
    return $ourNumber;
  }
  
  /**
   * Implementação da faixa livre do código de um boleto definido da
   * posição 20 à 44, com base nas regras da instituição pela qual o
   * boleto será emitido, conforme determinado pela FEBRABAN.
   *
   * @return string
   */
  public function getFreeField(): string
  {
    $sequentialNumber = $this->zeroFill($this->sequentialNumber, 8);
    $wallet           = $this->zeroFill($this->getWallet(), 3);
    $agencyNumber     = $this->zeroFill($this->emitterEntity->getAgencyNumber(), 4);
    $accountNumber    = $this->zeroFill($this->emitterEntity->getAccountNumber(), 5);
    
    // Carteira 198 - (Nosso Número com 15 posições) - Anexo 5 do manual
    if ( in_array($this->getWallet(),
      ['107', '122', '142', '143', '196', '198']) ) {
      if (($this->getWallet() === '198') && is_null($this->customerCode)) {
        // Caso não tenha sido informado o código do cliente e a carteira
        // seja 198, aborta
        throw new InvalidArgumentException("Não foi informado o código "
          . "do cliente que é obrigatório para carteira 198"
        );
      }

      $codigo = $wallet . $sequentialNumber
        . $this->zeroFill($this->getDocumentNumber(), 7)
        . $this->zeroFill($this->customerCode, 5)
      ;
      
      // Define o DV da carteira para a view
      $module = $this->checkSumMod10($codigo);
      $this->DACOfWalletAndOurNumber = $module;
      
      return $codigo . $module . '0';
    }
    
    // Geração do DAC - Anexo 4 do manual
    if (!in_array($this->getWallet(),
        ['126', '131', '146', '150', '168'])) {
      // Define o DV da carteira para a view
      $module = $this->checkSumMod10($agencyNumber . $accountNumber . $wallet . $sequentialNumber);
      $this->DACOfWalletAndOurNumber = $module;
    } else {
      // Define o DV da carteira para a view
      $module = $this->checkSumMod10($wallet . $sequentialNumber);
    }
    $this->DACOfWalletAndOurNumber = $module;
    
    // Módulo 10 Agência/Conta
    $dvAgConta = $this->checkSumMod10($agencyNumber . $accountNumber);
    
    return $wallet . $sequentialNumber . $module . $agencyNumber
      . $accountNumber . $dvAgConta . '000'
    ;
  }
  
  /**
   * Dados complementares para formatação do boleto.
   *
   * No caso do Itaú, apenas esconde campos não utilizados.
   *
   * @return array
   */
  public function getComplementaryData(): array
  {
    return [
      'wallet' => null
    ];
  }

  /**
   * Gerar o código do campo livre para as posições de 20 a 44.
   *
   * @param string $freeField
   *
   * @return array
   */
  public function parseFreeField(string $freeField): array
  {
   return [
     'agreement ' => null,
     'agencyNumber' => substr($freeField, 0, 4),
     'agencyNumberDAC' => null,
     'accountNumber' => substr($freeField, 17, 7),
     'accountNumberDAC' => null,
     'bankIdentificationNumber' => substr($freeField, 6, 11),
     'bankIdentificationNumberDAC' => null,
     'fullBankIdentificationNumber' => substr($freeField, 6, 11),
     'wallet' => substr($freeField, 4, 2)
   ];
  }
}
