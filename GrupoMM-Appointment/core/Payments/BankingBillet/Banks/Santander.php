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
 * Geração de boleto bancário baseado nas regras do Banco Santander
 * (033).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\Exception;

class Santander
  extends BankingBillet
{
  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 33;
  
  /**
   * O local de pagamento.
   *
   * @var string
   */
  protected $paymentPlace = "Pagar preferencialmente no Banco "
    . "Santander"
  ;

  // -----[ Dados da carteira ]-----------------------------------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = [ '101', '102', '201' ];
  
  /**
   * Define os nomes das carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $walletsNames = [
    '101' => 'Cobrança Simples ECR',
    '102' => 'Cobrança Simples CSR'
  ];

  // -----[ Fim dos dados da carteira ]---------------------------------

  // -----[ Campos adicionais ]-----------------------------------------
  
  /**
   * O valor do IOS - Seguradoras (Se 7% informar 7. Limitado a 9%).
   * Demais clientes usar 0 (zero).
   *
   * @var int
   */
  protected $IOS = 0;

  // -----[ Fim dos campos adicionais ]---------------------------------
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'santander.jpg';


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
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 6)
      . '-' . strval($this->emitterEntity->getDACOfAccountNumber())
    ;
  }
  
  /**
   * Define o valor do IOS. Seguradoras: se 7% informar 7 (limitado a 9%)
   * Demais clientes usar 0 (zero).
   *
   * @param int $IOS
   *   O valor do IOS
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setIOS(int $IOS)
  {
    $this->IOS = $IOS;
    
    return $this;
  }
  
  /**
   * Obtém o valor do IOS.
   *
   * @return int
   */
  public function getIOS(): int
  {
    return $this->IOS;
  }

  // =====[ Fim dos campos do boleto ]==================================
  
  /**
   * Constroi o número do banco para o título ("nosso número"), baseado
   * nas regras da instituição pela qual o boleto será emitido.
   *
   * @return string
   *
   * @throws InvalidArgumentException
   *   Em caso de algum parâmetro inválido
   */
  protected function buildBankIdentificationNumber(): string
  {
    return $this->zeroFill($this->sequentialNumber, 13);
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
    return '9' . $this->zeroFill($this->emitterEntity->getAccountNumber(), 7)
      . $this->getOurNumber()
      . $this->zeroFill($this->IOS, 1)
      . $this->zeroFill($this->getWallet(), 3)
    ;
  }
  
  /**
   * Dados complementares para formatação do boleto.
   *
   * No caso do Santander, apenas esconde o campo "Para uso do banco".
   *
   * @return array
   */
  public function getComplementaryData(): array
  {
    return [
      'hideForBankUse' => true,
    ];
  }
}
