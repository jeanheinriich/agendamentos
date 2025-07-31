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
 * Geração de boleto bancário baseado nas regras do Banco Regional de
 * Brasília (070).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\Exception;

class BRB
  extends BankingBillet
{
  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 70;
  
  /**
   * O local de pagamento.
   *
   * @var string
   */
  protected $paymentPlace = 'Até o vencimento pagar em qualquer Banco, '
    . 'depois só no BRB'
  ;

  // -----[ Dados da carteira ]-----------------------------------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = ['1', '2'];
  
  /**
   * Define os nomes das carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $walletsNames = [
    '1' => 'COB',
    '2' => 'COB'
  ];

  // -----[ Fim dos dados da carteira ]---------------------------------
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'BRB.jpg';


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
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 9)
      . '-' . strval($this->emitterEntity->getDACOfAccountNumber())
    ;
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
    return substr($this->getFreeField(), 13);
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
    $key = '000'
      . $this->zeroFill($this->emitterEntity->getAgencyNumber(), 3)
      . $this->zeroFill($this->emitterEntity->getAccountNumber(), 7)
      . $this->getWallet()
      . $this->zeroFill($this->sequentialNumber, 6)
      . $this->zeroFill($this->bankCode, 3)
    ;
    $d1 = $this->checkSumMod10($key);
    
    CalculaD2:
      $module = $this->checkSumMod11($key . $d1, 7);
      
      if ($module['remainder'] == 0) {
        $d2 = 0;
      } else if ($module['remainder'] > 1) {
        $d2 = 11 - $module['remainder'];
      } else if ($module['remainder'] == 1) {
        $d1 = $d1 + 1;
        
        if ($d1 == 10) {
          $d1 = 0;
        }
        
        goto CalculaD2;
      }
    
    return $chave . $d1 . $d2;
  }
  
  /**
   * Obtém o campo Agência/Cedente do boleto.
   *
   * @return string
   */
  public function getAgencyNumberAndEmitterCode(): string
  {
    return '000 - '
      . $this->getAgencyNumber()
      . ' - '
      . $this->getAccountNumber()
    ;
  }
}
