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
 * formatação de valores em um boleto que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;

use InvalidArgumentException;

trait FormatterTrait
{
  /**
   * Função que preenche com zeros a esquerda um valor, porém garantindo
   * que o valor não tenha mais dígitos do que o especificado.
   *
   * @param mixed $value
   *   O valor que desejamos formatar
   * @param int $size
   *   O tamanho final desejado
   *
   * @return string
   */
  protected function zeroFill($value, int $size): string
  {
    if (is_int($value)) {
      // Passamos um valor inteiro, então converte para texto
      $value = strval($value);
    }

    if (strlen($value) > $size) {
      throw new InvalidArgumentException("O valor {$value} possui mais "
        . "do que {$size} dígitos")
      ;
    }
    
    return str_pad($value, $size, '0', STR_PAD_LEFT);
  }
  
  /**
   * Formata o valor para apresentação em Real (1.000,00).
   *
   * @param float $value
   *   O valor a ser formatado
   * @param boolean $showZeroValues
   *   Se true, retorna 0,00 caso o valor esteja zerado
   *
   * @return string
   */
  protected function moneyFormat(float $value,
    $showZeroValues = false): string
  {
    return $value
      ? number_format($value, 2, ',', '.')
      : ($showZeroValues ? '0,00' : '')
    ;
  }
  
  /**
   * Formata o valor para apresentação em porcentage.
   *
   * @param float $value
   *   O valor a ser formatado
   * @param int $decimalPlaces
   *   A quantidade de casas decimais
   * @param boolean $showDecimalPlaces
   *   Se true, retorna as casas decimais mesmo que esteja zerada
   * @param boolean $showZeroValues
   *   Se true, retorna 0,00 caso o valor esteja zerado
   *
   * @return string
   */
  protected function percentFormat(float $value, int $decimalPlaces = 4,
    $showDecimalPlaces = true, $showZeroValues = false): string
  {
    $result = $value
      ? number_format($value, $decimalPlaces, ',', '.')
      : ($showZeroValues ? '0,' . str_repeat('0', $decimalPlaces) : '')
    ;

    if (!$showDecimalPlaces) {
      $result = trim($result, ',' . str_repeat('0', $decimalPlaces));
    }

    return $result;
  }

  /**
   * Função que obtém os N caracteres à esquerda de uma string.
   *
   * @param string $value
   *   O valor a ser analisado
   * @param int $num
   *   A quantidade de caracteres desejada
   *
   * @return string
   */
  protected function getLeftCharacters(string $value,
    int $num): string
  {
    return substr($value, 0, $num);
  }
  
  /**
   * Função que obtém os N caracteres à direita de uma string.
   *
   * @param string $value
   *   O valor a ser analisado
   * @param int $num
   *   A quantidade de caracteres desejada
   *
   * @return string
   */
  protected function getRightCharacters(string $value,
    int $num): string
  {
    return substr($value, strlen($value)-$num, $num);
  }

  /**
   * Define uma matriz com o texto, com no máximo $lines linhas. Linhas
   * adicionais serão ignoradas.
   *
   * @param mixed $text
   *   O texto ou linhas de texto
   * @param int $lines
   *   A quantidade limite de linhas no texto
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um valor para o texto inválido
   */
  protected function limitTextWithNLines($text, int $lines): array
  {
    // Verifica se o texto é uma matriz
    if (!is_array($text)) {
      // Não foi informada uma matriz, então verifica se o mesmo é um
      // texto
      if (is_string($text)) {
        $text = array($text);
      } else {
        throw new InvalidArgumentException("Informe um texto ou matriz "
          . "de texto."
        );
      }
    }
    
    // Completa a matriz com linhas adicionais se a mesma contiver menos
    // do que a quantidade de linhas necessárias
    while (count($text) < $lines) {
      $text[] = '';
    }
    
    // Remove linhas adicionais se a matriz com a descrição do
    // demonstrativo contiver mais do que 5 linhas
    if (count($text) > $lines) {
      return array_slice($text, 0, $lines);
    }

    return $text;
  }
}
