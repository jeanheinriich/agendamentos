<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Uma classe abstrata que define um indicador financeiro.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Indicators;

use DateTime;

abstract class AbstractIndicator
{
  /**
   * O código do indicador financeiro.
   *
   * @var int
   */
  protected static $code = 0;

  /**
   * A sigla do indicador financeiro.
   *
   * @var string
   */
  protected static $acronyms = 'unknown';

  /**
   * A sigla do instituto responsável pelo indicador.
   *
   * @var string
   */
  protected static $institute = 'unknown';

  /**
   * A data de obtenção do indicador;
   * 
   * @var DateTime
   */
  private $date;

  /**
   * O percentual na data deste indicador.
   * 
   * @var float
   */
  private $percentage;

  /**
   * O construtor de nosso indicador.
   *
   * @param DateTime $date
   *   A data dos valores do indicador.
   * @param float $percentage
   *   O percentual do indicador
   */
  public function __construct(DateTime $date, float $percentage)
  {
    $this->date = $date;
    $this->percentage = $percentage;
  }

  /**
   * Obtém o código do indicador.
   *
   * @return int
   */
  public static function getCode(): int
  {
    return static::$code;
  }

  /**
   * Obtém a sigla do indicador financeiro.
   *
   * @return string
   */
  public static function getAcronyms(): string
  {
    return static::$acronyms;
  }

  /**
   * Obtém o nome do instituto responsável.
   *
   * @return string
   */
  public static function getInstitute(): string
  {
    return static::$institute;
  }

  /**
   * Obtém o nome completo do indicador financeiro.
   *
   * @return string
   */
  public static function getName(): string
  {
    return static::$acronyms . ' (' . static::$institute . ')';
  }

  /**
   * Obtém a data de obtenção do indicador.
   *
   * @return DateTime
   */
  public function getDate(): DateTime
  {
    return $this->date;
  }

  /**
   * Obtém o percentual deste indicador na data.
   *
   * @return float
   */
  public function getPercentage(): float
  {
    return $this->percentage;
  }

  /**
   * Obtém o índice multiplicador para aplicar o percentual deste
   * indicador.
   *
   * @return float
   */
  public function getMultiplier(): float
  {
    return $this->multiplier;
  }
}