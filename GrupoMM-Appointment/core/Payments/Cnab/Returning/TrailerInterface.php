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
 * A interface para o fechamento de um arquivo de retorno no padrão Cnab
 * da FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning;

interface TrailerInterface
{
  /**
   * Obtém a quantidade de avisos.
   * 
   * @return int
   */
  public function getNotices(): int;

  /**
   * Define a quantidade de avisos.
   * 
   * @param int $notices
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setNotices(int $notices): object;

  /**
   * Obtém a quantidade de títulos.
   * 
   * @return int
   */
  public function getNumberOfBonds(): int;

  /**
   * Define a quantidade de títulos.
   * 
   * @param int $numberOfBonds
   *   A quantidade de títulos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setNumberOfBonds(int $numberOfBonds): object;

  /**
   * Obtém o valor dos títulos.
   * 
   * @return float
   */
  public function getValueOfBonds(): float;

  /**
   * Define o valor dos títulos.
   * 
   * @param float $valueOfBonds
   *   O valor dos títulos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setValueOfBonds(float $valueOfBonds): object;

  /**
   * Obtém a quantidade de títulos pagos.
   * 
   * @return int
   */
  public function getAmountOfPaid(): int;

  /**
   * Define a quantidade de títulos pagos.
   * 
   * @param int $amountOfPaid
   *   A quantidade de títulos pagos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfPaid(int $amountOfPaid): object;

  /**
   * Obtém a quantidade de títulos baixados.
   * 
   * @return int
   */
  public function getAmountOfRetired(): int;

  /**
   * Define a quantidade de títulos baixados.
   * 
   * @param int $amountOfRetired
   *   A quantidade de títulos baixados
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfRetired(int $amountOfRetired): object;

  /**
   * Obtém a quantidade de títulos que entraram.
   * 
   * @return int
   */
  public function getAmountOfEntries(): int;

  /**
   * Define a quantidade de títulos que entraram.
   * 
   * @param int $amountOfEntries
   *   A quantidade de títulos que entraram
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfEntries(int $amountOfEntries): object;

  /**
   * Obtém a quantidade de títulos modificados.
   * 
   * @return int
   */
  public function getAmountOfChanges(): int;

  /**
   * Define a quantidade de títulos modificados.
   * 
   * @param int $amountOfChanges
   *   A quantidade de títulos modificados
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfChanges(int $amountOfChanges): object;

  /**
   * Obtém a quantidade de títulos com erro.
   * 
   * @return int
   */
  public function getAmountOfErrors(): int;

  /**
   * Define a quantidade de títulos com erro.
   * 
   * @param int $amountOfErrors
   *   A quantidade de títulos com erro
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfErrors(int $amountOfErrors): object;

  /**
   * Converte o conteúdo do fechamento para matriz.
   * 
   * @return array
   */
  public function toArray(): array;
}