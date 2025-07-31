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
 * A interface para um indicador financeiro.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Indicators;

use DateTime;

interface Indicator
{
  /**
   * Obtém o código do indicador.
   *
   * @return int
   */
  public static function getCode(): int;

  /**
   * Obtém a sigla do indicador financeiro.
   *
   * @return string
   */
  public static function getAcronyms(): string;
  
  /**
   * Obtém o nome do instituto responsável.
   *
   * @return string
   */
  public static function getInstitute(): string;

  /**
   * Obtém o nome completo do indicador financeiro.
   *
   * @return string
   */
  public static function getName(): string;

  /**
   * Obtém a data do indicador.
   *
   * @return DateTime
   */
  public function getDate(): DateTime;

  /**
   * Obtém o percentual acumulado deste indicador nos últimos 12 meses
   * na data.
   *
   * @return float
   */
  public function getPercentage(): float;

  /**
   * Obtém o índice multiplicador para aplicar o percentual deste
   * indicador.
   *
   * @return float
   */
  public function getMultiplier(): float;
}