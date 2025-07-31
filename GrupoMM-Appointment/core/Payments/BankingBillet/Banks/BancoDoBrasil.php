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
 * Geração de boleto bancário baseado nas regras do Banco do Brasil
 * (001).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\Exception;
use InvalidArgumentException;

class BancoDoBrasil
  extends BankingBillet
{
  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 1;
  
  /**
   * O local de pagamento.
   *
   * @var string
   */
  protected $paymentPlace = 'Pagável em qualquer banco até o '
    . 'vencimento.'
  ;

  // -----[ Dados da carteira ]-----------------------------------------

  // Define as carteiras disponíveis para este banco. O Banco do Brasil
  // usa apenas o código
  //   11   : Cobrança simples com registro
  //   11-4 : Cobrança simples com registro convênio 4 dígitos
  //   12   : Cobrança Indexada com registro
  //   12-4 : Cobrança indexada com registro convênio 4 dígitos
  //   12-7 : Cobrança indexada com registro convênio 7 dígitos
  //   15   : Cobrança de prêmios de seguro com registro
  //   15-4 : Cobrança de prêmios de seguro com registro convênio 4 dígitos
  //   16   : Cobrança simples
  //   16-4 : Cobrança simples convênio 4 dígitos
  //   16-17: Cobrança simples nosso número 17 dígitos
  //   17   : Cobranca direta especial com registro
  //   17-4 : Cobranca direta especial com registro convênio 4 dígitos
  //   17-7 : Cobranca direta especial com registro convênio 7 dígitos
  //   18   : Cobrança simples nosso número 11 dígitos
  //   18-4 : Cobrança simples convênio 4 dígitos
  //   18-7 : Cobrança simples convênio 7 dígitos
  //   18-17: Cobrança simples nosso número 17 dígitos
  //   31   : Cobrança caucionada/vinculada com registro
  //   31-4 : Cobrança caucionada/vinculada com registro convênio 4 dígitos
  //   51   : Cobrança descontada com registro
  //   51-4   : Cobrança descontada com registro convênio 4 dígitos
  protected $wallets = [
    '11', '11-4',
    '12', '12-4', '12-7',
    '15', '15-4',
    '16', '16-4', '16-17',
    '17', '17-4', '17-7',
    '18', '18-4', '18-7', '18-17',
    '31', '31-4',
    '51', '51-4'
  ];

  // -----[ Fim dos dados da carteira ]---------------------------------

  // -----[ Campos adicionais ]-----------------------------------------
  
  /**
   * O número do convênio (4, 6 ou 7 caracteres)
   *
   * @var string
   */
  protected $agreementNumber;
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'bb.jpg';


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
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 8)
      . '-' . strval($this->emitterEntity->getDACOfAccountNumber())
    ;
  }
  
  /**
   * Define o número do convênio.
   *
   * @param string $agreementNumber
   *   O número do convênio
   * 
   * @return $this
   *   A instância do boleto
   */
  public function setAgreementNumber(string $agreementNumber): self
  {
    $this->agreementNumber = $agreementNumber;
    
    return $this;
  }
  
  /**
   * Obtém o número do convênio.
   *
   * @return string
   */
  public function getAgreementNumber(): string
  {
    return $this->agreementNumber;
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
    $agreementNumber = $this->getAgreementNumber();
    $ourNumber = null;
    
    switch ($this->wallet) {
      case '16-17':
        // Cobrança simples nosso número 17 dígitos
      case '18-17':
        // Cobrança simples nosso número 17 dígitos
        
        // Nosso número possui 17 posições livres sem dígito verificador
        $ourNumber = $this->zeroFill($this->sequentialNumber, 17);
        
        break;
      default:
        // O número varia conforme o número do convênio
        switch (strlen($this->agreementNumber)) {
          case 4:
            // Convênio de 4 posições
            
            // Formato nosso número com 11 dígitos
            // CCCCNNNNNNN-X, onde:
            //    CCCC : número do convênio fornecido pelo Banco
            //           (número fixo e não pode ser alterado)
            // NNNNNNN : sequêncial atribuído pelo cliente
            //       X : dígito verificador do "Nosso Número"
            $ourNumber = $this->zeroFill($this->agreementNumber, 4)
              . $this->zeroFill($this->sequentialNumber, 7)
            ;
            
            // Calcula o dígito verificador
            $module = $this->checkSumMod11($ourNumber);
            
            // Acrescenta o dígito verificador no final
            $ourNumber .= '-' . $module['digit'];
            
            break;
          case 6:
            // Convênio de 6 posições
            
            // Formato nosso número com 11 dígitos
            // CCCCCCNNNNN-X, onde:
            //  CCCCCC : número do convênio fornecido pelo Banco
            //           (número fixo e não pode ser alterado)
            //   NNNNN : sequêncial atribuído pelo cliente
            //       X : dígito verificador do "Nosso Número"
            $ourNumber = $this->zeroFill($this->agreementNumber, 6)
              . $this->zeroFill($this->sequentialNumber, 5)
            ;
            
            // Calcula o dígito verificador
            $module = $this->checkSumMod11($ourNumber);
            
            // Acrescenta o dígito verificador no final
            $ourNumber .= '-' . $module['digit'];
            
            break;
          case 7:
            // Convênio de 7 posições
            
            // Formata nosso número com 17 dígitos
            // CCCCCCCNNNNNNNNNN, onde:
            //     CCCCCCC: número do convênio fornecido pelo Banco
            //              (número fixo e não pode ser alterado)
            //  NNNNNNNNNN: sequêncial atribuído pelo cliente
            $ourNumber = $this->zeroFill($this->agreementNumber, 7)
              . $this->zeroFill($this->sequentialNumber, 10)
            ;
            
            break;
          default:
            // Número de convênio inválido
            throw new InvalidArgumentException("O número do convênio "
              . "informado é inválido"
            );
        }
        
        break;
    }
    
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
    // Descobre o tamanho do número do convênio
    $length = strlen($this->getAgreementNumber());
    
    // Monta o nosso número
    $ourNumber = $this->buildBankIdentificationNumber();
    
    switch ($this->wallet) {
      case '16-17':
        // Cobrança simples nosso número 17 dígitos
      case '18-17':
        // Cobrança simples nosso número 17 dígitos
        
        // Nosso número possui 17 posições livres sem dígito verificador
        
        // Precisa acrescentar o tipo de modalidade de cobrança
        // código "21" na posição 43 a 44
        
        // Convênio (6) + Nosso número (17) + Tipo de modalidade de cobrança (2)
        return $this->zeroFill($this->getAgreementNumber(), 6)
          . $ourNumber . '21'
        ;

        break;
      default:
        // O número varia conforme o número do convênio
        switch (strlen($this->agreementNumber)) {
          case 4:
            // Convênio de 4 posições
          case 6:
            // Convênio de 6 posições
            
            // Precisa retirar o dígito verificador
            $ourNumber = substr($ourNumber, 0, -2);
            
            // Nosso número sem DV (11) + Agencia (4) + Conta (8) + Carteira (2)
            return $ourNumber
              . $this->zeroFill($this->emitterEntity->getAgencyNumber(), 4)
              . $this->zeroFill($this->emitterEntity->getAccountNumber(), 8)
              . $this->getLeftCharacters($this->wallet, 2)
            ;
            
            break;
          case 7:
            // Convênio de 7 posições
            
            // Zeros (6) + Nosso número (17) + Carteira (2)
            return '000000'
              . $ourNumber
              . $this->getLeftCharacters($this->wallet, 2)
            ;
            
            break;
          default:
            // Número de convênio inválido
            throw new InvalidArgumentException("O número do convênio "
              . "informado é inválido"
            );
        }
    }
  }
}
