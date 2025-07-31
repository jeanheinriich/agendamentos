<?php
/*
 * This file is part of Extension Library.
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
 * Classe responsável pela geração das opções de parcelamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Providers;

class InstallmentProvider
{
  /**
   * Gera o conteúdo de uma pré-visualização de como ficará o
   * parcelamento em função dos parâmetros informados.
   * 
   * @param float $value
   *   O valor total do produto/serviço a ser adquirido
   * @param int $calculationFormula
   *   A fórmula de cálculo a ser utilizada        
   *     1: Juros simples
   *     2: Tabela Price
   * @param int $maxNumberOfInstallments
   *   Número máximo de parcelas
   * @param float $interestRate
   *   Taxa de juros
   * @param int $interestFrom
   *   Cobrar à partir da parcela
   * @param float $minimumInstallmentValue
   *   Menor valor de uma parcela
   * @param bool $selectable
   *   Se a tabela é selecionável (padrão não)
   *  
   * @return string
   */
  public function build(float $value, int $calculationFormula,
    int $maxNumberOfInstallments, float $interestRate,
    int $interestFrom, float $minimumInstallmentValue,
    bool $selectable = false): string
  {
    $class = '';
    if ($selectable){
      $class = "selectable ";
    }

    // Se não for fornecido um valor, aborta
    if (empty($value)) {
      if ($selectable) {
        return ''
          . '<div class="ui inverted pink installment '
          . $class . 'segment">'
          . '  <p class="installment interestFree">'
          . '    Nenhum valor cobrado informado'
          . '  </p>'
          . '</div>'
        ;
      } else {
        return ''
          . '<div id="errorMessage" class="ui error message">'
          . '  <div class="header">'
          . '    Não foi possível calcular o parcelamento'
          . '  </div>'
          . '</div>'
        ;
      }
    }
    
    // Se o valor fornecido for menor do que a parcela mínima, aborta
    if ($value <= $minimumInstallmentValue) {
      // Formata o valor da parcela única
      $installmentValue = number_format($value, 2, ',', '.');
      
      return ''
        . '<div class="ui raised secondary installment '
        . $class . 'segment">'
        . '  <p class="installment interestFree">'
        . '    1x de R$ ' . $installmentValue . ' sem juros'
        . '  </p>'
        . '</div>'
      ;
    }
    
    // Monta a tabela de parcelamento
    $installmentWithRateCount = 0;
    $installmentWithoutRateCount = 0;
    $content = '';
    for ($installment = 1; $installment <= $maxNumberOfInstallments; $installment++) {
      // Se esta parcela estiver na faixa que não têm juros
      if (    ($installment < $interestFrom)
           || ($interestFrom === 0)
           || ($interestRate == 0.000) ) {
        // Faz o cálculo do valor da parcela sem juros
        $installmentValue = $value / $installment;
      } else {
        // Faz o cálculo do valor da parcela levando em consideração os
        // juros, usando a regra de cálculo apropriada
        switch (intval($calculationFormula)) {
          case 2:
            // Calcula os juros pelas regras da tabela price
            $installmentValue = ($value * ($interestRate/100))/(1-(1/(pow(1+($interestRate/100), $installment))));
            
            break;
          default:
            // Calcula o valor da parcela usando juros simples
            $installmentValue = ($value / $installment) *
              (1 + $interestRate/100);
        }
      }
      
      // Verifica se o valor de parcela calculado é superior ao valor
      // mínimo de parcela
      if ($installmentValue >= $minimumInstallmentValue) {
        // Formata o valor da parcela e o total
        $totalValue = number_format($installmentValue * $installment, 2, ',', '.');
        $installmentValue = number_format($installmentValue, 2, ',', '.');
        
        if (($installment < $interestFrom) || ($interestFrom === 0) || ($interestRate === 0.000)) {
          // Incrementa a quantidade de parcelas sem juros
          $installmentWithoutRateCount++;
          $classes = ($class)
            ? ' class="' . trim($class) . '"'
            : ''
          ;
          
          $content .= ''
            . "<tr{$classes}>"
            . '  <td class="center aligned">'
            . $installment
            . '  </td>'
            . '  <td class="center aligned">&times;</td>'
            . '  <td class="right aligned">'
            . '    R$&nbsp;' . $installmentValue
            . '  </td>'
            . '  <td>sem juros</td>'
            . '  <td class="right aligned">'
            . '    R$&nbsp;' . $totalValue
            . '  </td>'
            . '  <td class="center aligned">'
            . '    <i class="star icon yellow"></i>'
            . '  </td>'
            . '</tr>'
          ;
        } else {
          // Incrementa a quantidade de parcelas com juros
          $installmentWithRateCount++;
          
          $content .= ""
            . '<tr class="' . $class . 'withInterest">'
            . '  <td class="center aligned">'
            . $installment
            . '  </td>'
            . '  <td class="center aligned">&times;</td>'
            . '  <td class="right aligned">'
            . '    R$&nbsp;' . $installmentValue
            . '  </td>'
            . '  <td>com juros</td>'
            . '  <td class="right aligned">'
            . '    R$&nbsp;' . $totalValue
            . '  </td>'
            . '  <td class="center aligned">'
            . '    <i class="star outline icon" '
            . '       style="color: #909090 !important;">'
            . '    </i>'
            . '  </td>'
            . '</tr>'
          ;
        }
      }
    }
    
    // Monta o resultado do parcelamento
    $result = ''
      . '<table class="ui inverted blue striped installment '
      . $class . 'unstackable table">'
    ;
    if ($installmentWithoutRateCount > 1) {
      $result .= ''
        . '<thead>'
        . '  <tr>'
        . '    <th colspan="6">'
        . '      Parcele em até '
        . '      <b>'
        . $installmentWithoutRateCount . ' vezes sem juros'
        . '      </b>'
        . '    </th>'
        . '  </tr>'
        . '</thead>'
      ;
    } else {
      if ($installmentWithRateCount > 0) {
        $result .= ''
          . '<thead>'
          . '  <tr>'
          . '    <th colspan="6">'
          . '      Parcele em até '
          . '      <b>'
          . ($installmentWithoutRateCount + $installmentWithRateCount)
          . '        vezes'
          . '      </b>'
          . '    </th>'
          . '  </tr>'
          . '</thead>'
        ;
      }
    }
    $result .= '<tbody>' . $content . '</tbody>';
    $result .= '</table>';
    
    return $result;
  }
}
