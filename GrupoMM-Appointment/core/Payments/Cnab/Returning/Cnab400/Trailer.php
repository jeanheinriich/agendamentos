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
 * O fechamento do arquivo de remessa.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning\Cnab400;

use Core\Payments\Cnab\MagicTrait;
use Core\Payments\Cnab\Returning\TrailerInterface;

class Trailer
  implements TrailerInterface
{
  /**
   * Importa as características para criação dos métodos mágicos.
   */
  use MagicTrait;

  /**
   * A quantidade de avisos.
   * 
   * @var int
   */
  protected $notices = 0;

  /**
   * Número de títulos.
   * 
   * @var int
   */
  protected $numberOfBonds;

  /**
   * @var float
   */
  protected $valueOfBonds;

  /**
   * Número de títulos pagos.
   * 
   * @var int
   */
  protected $amountOfPaid = 0;

  /**
   * Número de títulos baixados.
   * 
   * @var int
   */
  protected $amountOfRetired = 0;

  /**
   * Número de títulos que deram entrada.
   * 
   * @var int
   */
  protected $amountOfEntries = 0;

  /**
   * Número de títulos modificados.
   * 
   * @var int
   */
  protected $amountOfChanges = 0;

  /**
   * Número de títulos com erro.
   * 
   * @var int
   */
  protected $amountOfErrors = 0;

  /**
   * Obtém a quantidade de avisos.
   * 
   * @return int
   */
  public function getNotices(): int
  {
    return $this->notices;
  }

  /**
   * Define a quantidade de avisos.
   * 
   * @param int $notices
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setNotices(int $notices): self
  {
    $this->notices = $notices;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos.
   * 
   * @return int
   */
  public function getNumberOfBonds(): int
  {
    return $this->numberOfBonds ?: 0;
  }

  /**
   * Define a quantidade de títulos.
   * 
   * @param int $numberOfBonds
   *   A quantidade de títulos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setNumberOfBonds(int $numberOfBonds): self
  {
    $this->numberOfBonds = $numberOfBonds;

    return $this;
  }

  /**
   * Obtém o valor dos títulos.
   * 
   * @return float
   */
  public function getValueOfBonds(): float
  {
    return $this->valueOfBonds ?: 0.00;
  }

  /**
   * Define o valor dos títulos.
   * 
   * @param float $valueOfBonds
   *   O valor dos títulos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setValueOfBonds(float $valueOfBonds): self
  {
    $this->valueOfBonds = $valueOfBonds;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos pagos.
   * 
   * @return int
   */
  public function getAmountOfPaid(): int
  {
    return $this->amountOfPaid;
  }

  /**
   * Define a quantidade de títulos pagos.
   * 
   * @param int $amountOfPaid
   *   A quantidade de títulos pagos
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfPaid(int $amountOfPaid): self
  {
    $this->amountOfPaid = $amountOfPaid;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos baixados.
   * 
   * @return int
   */
  public function getAmountOfRetired(): int
  {
    return $this->amountOfRetired;
  }

  /**
   * Define a quantidade de títulos baixados.
   * 
   * @param int $amountOfRetired
   *   A quantidade de títulos baixados
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfRetired(int $amountOfRetired): self
  {
    $this->amountOfRetired = $amountOfRetired;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos que entraram.
   * 
   * @return int
   */
  public function getAmountOfEntries(): int
  {
    return $this->amountOfEntries;
  }

  /**
   * Define a quantidade de títulos que entraram.
   * 
   * @param int $amountOfEntries
   *   A quantidade de títulos que entraram
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfEntries(int $amountOfEntries): self
  {
    $this->amountOfEntries = $amountOfEntries;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos modificados.
   * 
   * @return int
   */
  public function getAmountOfChanges(): int
  {
    return $this->amountOfChanges;
  }

  /**
   * Define a quantidade de títulos modificados.
   * 
   * @param int $amountOfChanges
   *   A quantidade de títulos modificados
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfChanges(int $amountOfChanges): self
  {
    $this->amountOfChanges = $amountOfChanges;

    return $this;
  }

  /**
   * Obtém a quantidade de títulos com erro.
   * 
   * @return int
   */
  public function getAmountOfErrors(): int
  {
    return $this->amountOfErrors;
  }

  /**
   * Define a quantidade de títulos com erro.
   * 
   * @param int $amountOfErrors
   *   A quantidade de títulos com erro
   *
   * @return $this
   *   A instância do fechamento
   */
  public function setAmountOfErrors(int $amountOfErrors): self
  {
    $this->amountOfErrors = $amountOfErrors;

    return $this;
  }
}
