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
 * Federal - CEF (104), no modelo SIGCB.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Banks;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\Exception;

class Caixa
  extends BankingBillet
{
  /**
   * O código do banco.
   *
   * @var int
   */
  protected $bankCode = 104;
  
  /**
   * O local de pagamento.
   *
   * @var string
   */
  protected $paymentPlace = "PREFERENCIALMENTE NAS CASAS LOTÉRICAS ATÉ "
    . "O VALOR LIMITE"
  ;

  // -----[ Dados da carteira ]-----------------------------------------
  
  /**
   * Define as carteiras disponíveis para este banco.
   *
   * @var array
   */
  protected $wallets = [ 'SR', 'RG' ];

  // -----[ Fim dos dados da carteira ]---------------------------------
  
  /**
   * Nome do arquivo do layout a ser usado (modelo de boleto).
   *
   * A Caixa adota campos não presentes no projeto original do boleto,
   * sendo necessário utilizar o layout diferente, além de alterar
   * cedente para beneficiário e sacado para pagador. Segundo o banco,
   * estas regras muitas vezes não são observadas na homologação, mas,
   * considerando o caráter subjetivo de quem vai analisar na Caixa,
   * preferi incluir todos de acordo com o manual. Para conhecimento,
   * foi copiado o modelo 3.5.1 adaptado.
   * 
   * Também foram removidos os campos espécie, REAL, quantidade e valor
   * por considerar desnecessários e não obrigatórios.
   *
   * @var string
   */
  protected $layout = 'CEF.phtml';
  
  /**
   * Arquivo contendo a logomarca da instituição financeira.
   *
   * @var string
   */
  protected $bankLogoImage = 'caixa.jpg';


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
    return $this->zeroFill($this->emitterEntity->getAccountNumber(), 8)
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
    // Inicia o número de acordo com o tipo de cobrança, provavelmente
    // só será usado Sem Registro, mas se futuramente permitir a geração
    // de lotes para inclusão, o tipo registrado pode ser útil.
    // 
    // Os valores são:
    //   1 => registrada,
    //   2 => sem registro
    // 
    // Acrescenta-se o número 4 para indicar que é o beneficiário quem
    // está gerando o boleto
    if ($this->wallet == 'SR') {
      $wallet = '24';
    } else {
      $wallet = '14';
    }
    
    // As 15 próximas posições no nosso número são a critério do
    // beneficiário, utilizando o sequencial. Depois, calcula-se o
    // código verificador por módulo 11
    $module = self::Mod11($wallet
      . $this->zeroFill($this->sequentialNumber, 15)
    );
    
    $ourNumber .= $this->zeroFill($this->sequentialNumber, 15)
      . '-' . $module['digit']
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
    // O Campo Livre contém 25 posições dispostas da seguinte forma:
    // 
    // Descrição -------------------- Posição no Código de Barras --- Observação
    // 
    // Código do Beneficiário ------- Posição: 20-25
    // DV do Código do Beneficiário - Posição: 26-26 ---------------- ANEXO VI
    // Nosso Número – Seqüência 1 --- Posição: 27-29 ---------------- 3ª a 5ª posição do Nosso Número
    // Constante 1 ------------------ Posição: 30-30 ---------------- 1ª posição do Nosso Numero:
    //                                                                (1-Registrada / 2-Sem Registro)
    // Nosso Número – Seqüência 2 --- Posição: 31-33 ---------------- 6ª a 8ª posição do Nosso Número
    // Constante 2 ------------------ Posição: 34-34 ---------------- 2ª posição do Nosso Número:
    //                                                                Ident da Emissão do Boleto (4-Beneficiário)
    // Nosso Número – Seqüência 3 --- Posição: 35-43 ---------------- 9ª a 17ª posição do Nosso Número
    // DV do Campo Livre ------------ Posição: 44-44 ---------------- Item 5.3.1 (abaixo) 
    $ourNumber  = $this->buildBankIdentificationNumber();
    $emitter    = $this->zeroFill($this->emitterEntity->getAccountNumber(), 6);
    
    // Código do beneficiário + DV
    $module = self::Mod11($emitter);
    $freeField = $emitter . $module['digit'];
    
    // Sequencia 1 (posições 3-5 NN) + Constante 1 (1 => registrada,
    // 2 => sem registro). Acrescenta-se o número 4 para indicar que é o
    // beneficiário quem está gerando o boleto.
    if ($this->wallet == 'SR') {
      $wallet = '2';
    } else {
      $wallet = '1';
    }
    $freeField .= substr($ourNumber, 2, 3) . $wallet;
    
    // Sequencia 2 (posições 6-8 NN) + Constante 2 (4-Beneficiário)
    $freeField .= substr($ourNumber, 5, 3) . '4';
    
    // Sequencia 3 (posições 9-17 NN)
    $freeField .= substr($ourNumber, 8, 9);
    
    // DV do Campo Livre
    $module = self::Mod11($freeField);
    $freeField .= $module['digit'];
    
    return $freeField;
  }
}
