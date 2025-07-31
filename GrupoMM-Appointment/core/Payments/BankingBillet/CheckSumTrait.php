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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * calculo de dígitos verificadores (DAC) que outras classes podem
 * incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;


trait CheckSumTrait
{
  /**
   * Calcula o dígito verificador usando o algoritmo Módulo 10.
   *
   * @param string $num
   *   O número cujo dígito verificador desejamos calcular
   *
   * @return string
   *   O dígito verificador
   */
  protected function checkSumMod10(string $num): string
  {
    // O totalizador
    $total  = 0;
    
    // O peso
    $weight = 2;
    
    // Separacao dos números
    for ($i = strlen($num); $i > 0; $i--) {
      // Pega cada número isoladamente
      $numeros[$i] = substr($num,$i-1,1);
      
      // Cada dígito no número é multiplicado pelo seu peso
      $temp = $numeros[$i] * $weight;
      $temp0=0;
      
      foreach (preg_split('// ',$temp,-1,PREG_SPLIT_NO_EMPTY) as $v) {
        $temp0 += $v;
      }
      
      $partial10[$i] = $temp0;
      
      // Monta a sequência para soma dos dígitos no modulo 10
      $total += $partial10[$i];
      
      // Intercala o fator de multiplicação (peso)
      if ($weight == 2) {
        $weight = 1;
      } else {
        $weight = 2;
      }
    }
    
    $remainder  = $total % 10;
    $verifyingDigit = 10 - $remainder;
    
    // Torná-lo zero se o dígito de verificação for 10
    $verifyingDigit = ($verifyingDigit == 10)
      ? 0
      : $verifyingDigit
    ;
    
    return strval($verifyingDigit);
  }
  
  /**
   * Calcula o dígito verificador utilizando o algorítmo de módulo 11.
   * Este algorítmo possui algumas variantes, que são o uso de base 9
   * (padrão) e base 7, bem como a possibilidade de letras para alguns
   * resultados.
   *
   * @param string $value
   *   O número para o qual desejamos calcular o dígito verificador
   * @param int|integer $maxFactor
   *   Estipula o fator máximo de múltiplicação a ser aplicado em cada
   *   algarismo do número informado (O padrão é 9)
   * @param string $ifTen
   *   Caso o resto da divisão seja 10, o que colocar em seu lugar?
   *   Existem bancos que adicionam '0', e outros valores como '1', 'X',
   *   'P', etc
   * @param string $ifZero
   *   Se o resultado for zero, substituir por algum outro valor? (O
   *   padrão é '0')
   *
   * @return string
   *   O dígito verificador calculado
   */
  protected function checkSumMod11(
    string $value,
    int $maxFactor = 9,
    $ifTen = '0',
    $ifZero = '0'
  ): string
  {
    // O totalizador
    $total  = 0;
    
    // O peso
    $weight = 2;
    
    // Loop através do número base
    $numbers = [];
    for ($i = strlen($value); $i > 0; $i--) {
      // Pega cada dígito isoladamente
      $numbers[$i] = substr($value, $i-1, 1);
      
      // Cada dígito no número é multiplicado pelo seu peso
      $partial[$i] = $numbers[$i] * $weight;
      
      // Soma os resultados da multiplicação
      $total += $partial[$i];
      
      // Incrementa o peso
      if ($weight == $maxFactor) {
        // Restaura o peso (fator de multiplicação)
        $weight = 1;
      }

      $weight++;
    }

    // Calcula o resto da divisão
    $remainder = ($total * 10) % 11;

    // Conforme o resto, determinamos algumas condições
    switch ($remainder) {
      case 0:
        // O resultado é o valor de $ifZero
        $DAC = $ifZero;

        break;
      case 10:
        $DAC = $ifTen;

        break;
      default:
        $DAC = (string) $remainder;

        break;
    }

    return $DAC;
  }
}
