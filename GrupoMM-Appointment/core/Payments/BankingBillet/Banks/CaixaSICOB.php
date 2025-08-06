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
 * Geração de boleto bancário baseado nas regras da Caixa Economica
 * Federal - CEF (104), no modelo SICOB.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\Exception;

class CaixaSICOB
  extends Caixa
{
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
    // Inicia o número de acordo com o tipo de cobrança, provavelmente
    // só será usado Sem Registro, mas se futuramente permitir a geração
    // de lotes para inclusão, o tipo registrado pode ser útil.
    // 
    // Os valores são:
    //   9 => registrada,
    //   8 => sem registro
    if ($this->wallet == 'SR') {
      $wallet = '8';
    } else {
      $wallet = '9';
    }
    
    // As 8 próximas posições no nosso número são a critério do
    // beneficiário, utilizando o sequencial. Depois, calcula-se o
    // código verificador por módulo 11
    $module = self::Mod11($wallet
      . self::zeroFill($this->sequentialNumber, 8)
    );
    
    $ourNumber .= self::zeroFill($this->sequentialNumber, 8) . '-'
      . $module['digit']
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
    $freeField = substr($this->buildBankIdentificationNumber(), 0, 10)
      . $this->getAgencyNumber()
      . $this->zeroFill($this->emitterEntity->getAccountNumber(), 11)
    ;
    
    return $freeField;
  }
}
