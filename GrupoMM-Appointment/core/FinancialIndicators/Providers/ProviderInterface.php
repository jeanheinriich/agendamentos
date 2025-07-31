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
 * A interface para um provedor de indicadores financeiros.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Providers;

use Core\FinancialIndicators\Indicators\Indicator;

interface ProviderInterface
{
  /**
   * Obtém o nome do provedor de indicadores financeiros.
   *
   * @return string
   */
  public function getProviderName(): string;

  /**
   * Obtém os valores mais recentes de cada indicador financeiro.
   *
   * @return array
   */
  public function getLatestIndicators(): array;

  /**
   * Obtém os indicadores para o mês atual.
   *
   * @return array
   */
  public function getIndicatorsForCurrentMonth(): array;
  
  /**
   * Obtém os índices disponíveis através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return array
   *   Os índices deste indicador financeiro
   */
  public function getIndexesFromIndicatorCode(
    int $indicatorCode
  ): array;

  /**
   * Obtém o índice atual através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return null|Indicator
   *   O índice atual deste indicador financeiro, ou nulo se o mesmo não
   *   estiver disponível
   */
  public function getCurrentIndexFromIndicatorCode(
    int $indicatorCode
  ): ?Indicator;
}
