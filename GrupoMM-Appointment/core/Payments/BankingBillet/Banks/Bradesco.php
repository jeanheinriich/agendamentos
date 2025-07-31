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
 * Geração de boleto bancário baseado nas regras do Banco Bradesco
 * (237).
 *
 * Layout do código de barras:
 * 
 *   1   5   10   15   20   25   30   35   40  44
 *   │   │    │    │    │    │    │    │    │   │
 *   23796307200000666060148170000000005100348510
 *   23798307100000666060148170000000005100348510
 *   └┬┘↓↓└─┬┘└────┬───┘└─┬┘└┤└────┬────┘└──┬──┘↓
 *    │ ││  │      │      │  │     │        │   └─╼ Zero fixo
 *    │ ││  │      │      │  │     │        └─────╼ Conta benefeciário
 *    │ ││  │      │      │  │     └──────────────╼ Nosso número
 *    │ ││  │      │      │  └────────────────────╼ Carteira
 *    │ ││  │      │      └───────────────────────╼ Agência benefeciário
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

class Bradesco
  extends AbstractBankingBillet
{
  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 237;
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'bradesco.png';


  // -----[ Informações do contrato com o banco emissor ]---------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = [ '2', '4', '9', '21', '26', '28' ];

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
    'RC'  => 'Recibo',
    'LC'  => 'Letras de câmbio',
    'ND'  => 'Nota de débito',
    'DS'  => 'Duplicata de serviços',
    'BP'  => 'Boleto de proposta',
    'O'   => 'Outros'
  ];

  /**
   * Códigos de espécies de documento, para uso na remessa.
   *
   * @var array
   */
  protected $kindOfDocumentCodes = [
    240 => [
      'DM' => '01', // Duplicata mercantil
      'NP' => '02', // Nota promissória
      'NS' => '03', // Nota de seguro
      'CS' => '04', // Cobrança seriada
      'RC' => '05', // Recibo
      'LC' => '10', // Letras de câmbio
      'ND' => '11', // Nota de débito
      'DS' => '12', // Duplicata de serviço
      'BP' => '30', // Boleto de proposta
      'O'  => '99'  // Outros
    ],
    400 => [
      'DM'  => '01', // Duplicata mercantil
      'NP'  => '02', // Nota promissória
      'NS'  => '03', // Nota de seguro
      'CS'  => '04', // Cobrança seriada
      'RC'  => '05', // Recibo
      'LC'  => '10', // Letra de câmbio
      'ND'  => '11', // Nota de débito
      'DS'  => '12', // Duplicata de serviço
      'CC'  => '31', // Cartão de crédito
      'BDP' => '32', // Boleto de proposta
      'O'   => '99', // Outros
    ]
  ];


  // -----[ Campos adicionais ]-----------------------------------------
  
  /**
   * Trata-se de um código utilizado para identificar mensagens
   * especificas ao cedente, sendo que o mesmo consta no cadastro do
   * Banco. Quando não houver código cadastrado preencher com zeros
   * "000".
   *
   * @var string
   */
  protected $CIP = '000';


  // =====[ Início dos campos do boleto ]===============================

  /**
   * Obtém o número da agência formatado conforme os padrões do banco.
   *
   * @return string
   */
  public function getAgencyNumber(): string
  {
    return $this->zeroFill($this->emitterEntity->getAgencyNumber(), 4)
      . '-' . strval($this->emitterEntity->getDACOfAgencyNumber())
    ;
  }

  /**
   * Obtém o número da conta formatado conforme os padrões do banco.
   *
   * @return string
   */
  public function getAccountNumber(): string
  {
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 7)
      . '-' . strval($this->emitterEntity->getDACOfAccountNumber())
    ;
  }
  
  /**
   * Define o valor do CIP.
   *
   * @param string $CIP
   *   O CIP
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setCIP(string $CIP): self
  {
    $this->CIP = $CIP;
    
    return $this;
  }
  
  /**
   * Obtém o valor do CIP.
   *
   * @return string
   */
  public function getCIP(): string
  {
    return $this->CIP;
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
    if (!in_array($this->wallet, $this->getWallets())) {
      throw new InvalidArgumentException("Informe um dos códigos de "
        . "carteira disponíveis para este banco"
      );
    }

    $DAC = $this->checkSumMod11(
      $this->zeroFill($this->wallet, 2)
      . $this->zeroFill($this->sequentialNumber, 11), 7, 'P', '0'
    );

    return $this->zeroFill($this->sequentialNumber, 11) . $DAC;
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
    return ''
      . $this->zeroFill($this->wallet, 2)
      . ' / '
      .  substr_replace($this->getBankIdentificationNumber(), '-', -1, 0)
    ;
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
    if ($this->freeField) {
      return $this->freeField;
    }

    $this->freeField = ''
      . $this->zeroFill($this->emitterEntity->getAgencyNumber(), 4)
      . $this->zeroFill($this->getWallet(), 2)
      . $this->zeroFill($this->sequentialNumber, 11)
      . $this->zeroFill($this->emitterEntity->getAccountNumber(), 7)
      . '0'
    ;

    return $this->freeField;
  }

  /**
   * Dados complementares para formatação do boleto.
   *
   * No caso do Bradesco, acrescenta as informações do CIP.
   *
   * @return array
   */
  public function getComplementaryData(): array
  {
    return [
      'CIP' => $this->zeroFill($this->getCIP(), 3),
      'showCIP' => true,
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
