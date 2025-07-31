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
 * Essa é uma trait (característica) simples de funções auxiliares para
 * lidar com queries que outras classes podem importar.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Controllers;

trait QueryTrait
{
  /**
   * Inclui num campo de ordenação a direção informada no padrão do SQL.
   * 
   * @param string $orderFields
   *   Os campos separados por vírgula
   * @param string $orderDir
   *   O ordenamento dos campos
   * 
   * @return string
   *   O campo de ordenação modificado
   */
  protected function formatOrderBy(
    string $orderFields,
    string $orderDir
  ): string
  {
    $holds = [];
    $funcExp = '/(?:\w*[.])??\w*\(([^)]+)\)/i';

    // Separamos, se necessário, a chamada de funções nos campos de
    // ordenamento
    if (preg_match($funcExp, $orderFields)) {
      // Encontramos uma ou mais chamadas de função, subsituímos elas
      // por valores especiais
      $count = 0;
      do {
        preg_match($funcExp, $orderFields, $matches, PREG_OFFSET_CAPTURE, 0);
        $holds[] = $matches[0][0];
        $orderFields = preg_replace($funcExp, "_{$count}", $orderFields, 1);
        $count++;
      } while (preg_match($funcExp, $orderFields));
    }

    // Separamos os campos separados por vírgula
    $parts = explode(',', $orderFields);

    // Analisamos cada campo, acrescentando o parâmetro de ordenamento
    $orders = [];
    foreach ($parts AS $field) {
      $field = trim($field);

      if (preg_match('/(?:\w*[.])??\w*\s\w*\s\w*/i', $field)) {
        // Se tiver clausulas adicionais, não alteramos.
        // Ex: field NULLS FIRST
        $orders[] = $field;
      } else {
        // Verificamos se foi feita uma substituição prévia de função
        if (preg_match('/^_(\w*)/i', $field, $matches, PREG_OFFSET_CAPTURE, 0)) {
          $field = $holds[ intval($matches[1][0]) ];
        }

        $orders[] = $field . ' ' . $orderDir;
      }
    }
    
    return implode(', ', $orders);
  }

  /**
   * Mecanismo para analisar condições de pesquisa de maneira binária.
   * 
   * @param bool $flags
   *   O resultado de cada condição
   * 
   * @return integer
   *   As flags como um número inteiro
   */
  protected function binaryFlags(bool ...$flags): int
  {
    // Pega cada uma das flags e adiciona ao nosso valor binário
    $binaryString = "";
    foreach ($flags as $flag) {
      $binaryString = ($flag?"0":"1") . $binaryString;
    }
    
    // Converte em decimal e retorna
    return base_convert($binaryString, 2, 16);
  }
  
  /**
   * Converte um valor para ponto flutuante (float).
   *
   * @param mixed $value
   *   O valor a ser convertido
   *
   * @return float
   */
  protected function toFloat($value): float
  {
    return floatval(
      str_replace(
        ',', '.',
        str_replace(
          '.', '',
          $value
        )
      )
    );
  }

  /**
   * Converte uma matriz numa representação compatível com o padrão SQL
   * do Postgres.
   *
   * @param array $set
   *   A matriz a ser convertida
   *
   * @return string
   *   A matriz formatada no padrão SQL
   */
  protected function toSQLArray(array $set): string
  {
    $result = [];
    foreach ($set AS $tupla) {
      if (is_array($tupla)) {
        // Chamamos recursivamente esta função
        $result[] = $this->toSQLArray($tupla);
      } else {
        // Escapamos aspas duplas
        $tupla = str_replace('"', '\\"', $tupla);

        if (!is_numeric($tupla)) {
          // Inclui aspas duplas apenas em valores não numéricos
          $tupla = '"' . $tupla . '"';
        }

        $result[] = $tupla;
      }
    }

    return '{' . implode(",", $result) . '}';
  }
}
