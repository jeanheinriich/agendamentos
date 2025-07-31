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
 * Uma classe abstrata que define os dados de uma transação numa
 * operação de transferência bancária.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankTransfer;

use Core\Payments\FinancialAgent;

abstract class Payload
{
  /**
   * Entidade cedente (emissor).
   *
   * @var FinancialAgent
   */
  protected $emitterEntity;

  /**
   * Descrição do pagamento sendo realizado.
   * 
   * @var string
   */
  protected $description;

  /**
   * ID da transação.
   * 
   * @var string
   */
  protected $transactionID;

  /**
   * Valor da transação.
   * 
   * @var float
   */
  protected $amount;

  /**
   * Define a descrição do pagamento sendo realizado.
   * 
   * @param string $description
   *   A descrição do pagamento
   * 
   * @return $this
   *   A instância do payload
   */
  public function setDescription(string $description): self
  {
    $this->description = $description;

    return $this;
  }

  /**
   * Define a entidade cedente (emissor).
   *
   * @param FinancialAgent $emitterEntity
   *   A entidade cedente
   * 
   * @return $this
   *   A instância do payload
   */
  public function setEmitterEntity(FinancialAgent $emitterEntity): self
  {
    $this->emitterEntity = $emitterEntity;
    
    return $this;
  }
  
  /**
   * Obtém a entidade cedente (emissor).
   *
   * @return FinancialAgent
   */
  public function getEmitter(): FinancialAgent
  {
    return $this->emitterEntity;
  }

  /**
   * Define a identificação única desta transação.
   * 
   * @param string $transactionID
   *   O ID da transação
   * 
   * @return $this
   *   A instância do payload
   */
  public function setTransactionID(string $transactionID): self
  {
    $this->transactionID = $transactionID;

    return $this;
  }

  /**
   * Define o valor da transação.
   * 
   * @param float $amount
   *   O valor da transação
   * 
   * @return $this
   *   A instância do payload
   */
  public function setAmount(float $amount): self
  {
    $this->amount = $amount;

    return $this;
  }

  /**
   * Método responsável por gerar o código completo do payload.
   * 
   * @return string
   */
  abstract public function getPayload();
}