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
 * formatação de valores em um arquivo CNAB que outras classes podem
 * incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

use RangeException;
use InvalidArgumentException;

trait FormatterTrait
{
  /**
   * Retorna o valor convertido para maiúsculo.
   *
   * @param string $value
   *   O valor a ser convertido
   *
   * @return string
   *   O valor convertido para maiúsculo
   */
  protected function toUpper(string $value): string
  {
    return strtr(mb_strtoupper($value),
      "àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ",
      "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß"
    );
  }

  /**
   * Função para normalizar uma string, retirando acentos.
   *
   * @param string $value
   *   O valor a ser normalizado
   * 
   * @return string
   *   O valor normalizado
   */
  protected function normalizeChars(string $value): string
  {
    // A tabela de substituições
    $replacementTable = array(
      'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ä' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
      'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'Eth',
      'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
      'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ŕ' => 'R',

      'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ä' => 'a', 'æ' => 'ae', 'ç' => 'c',
      'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'eth',
      'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
      'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ŕ' => 'r', 'ÿ' => 'y',

      'ß' => 'sz', 'þ' => 'thorn', 'º' => '', 'ª' => '', '°' => '',
    );

    return preg_replace('/[^0-9a-zA-Z !*\-$\(\)\[\]\{\},.;:\/\\#%&@+=]/',
      '',
      strtr($value, $replacementTable)
    );
  }

  /**
   * Adiciona um campo em uma linha na posição informada.
   *
   * @param int $startAt
   *   A posição de início
   * @param int $endAt
   *   A posição de término
   * @param mixed $value
   *   O valor sendo adicionado
   *
   * @throws InvalidArgumentException
   *   Em caso de algum dos argumentos sejam inválidos
   * @throws RangeException
   *   Em caso do campo ultrapassar os limites da linha
   */
  public function add(int $startAt, int $endAt, $value)
  {
    if ($endAt < $startAt) {
      throw new InvalidArgumentException("O início do campo ($startAt) "
        . "não pode ser superior ao seu final ($endAt)"
      );
    }

    $startAt--;

    if ($endAt > $this->lineLength) {
      throw new RangeException("Ultrapassado o limite do comprimento "
        . "da linha: informado {$endAt} e o máximo é de "
        . "{$this->lineLength} caracteres"
      );
    }

    if (is_string($value)) {
      // Analisamos o tamanho do conteúdo
      $size = $endAt - $startAt;
      if (mb_strlen($value) > $size) {
        throw new InvalidArgumentException("O valor informado '{$valor}' "
          . "possui comprimento de " . mb_strlen($value) . " e que é "
          . "maior do que o esperado ({$size}"
        );
      }
    }

    $value = sprintf("%{$size}s", $value);
    $value = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) + array_fill(0, $size, '');

    return array_splice($this->currentLine, $startAt, $size, $value);
  }
  
  /**
   * Formata um campo em um arquivo CNAB.
   *
   * @param string $mask
   *   A máscara do campo
   * @param string $value
   *   O valor do campo
   * @param int $size
   *   O tamanho em caracteres do campo
   * @param integer $decimalPlaces
   *   A quantidade de casas decimais
   * @param string $fillCharacter
   *   O caracter de preenchimento
   *
   * @return string
   */
  protected function formatField(string $mask, string $value, int $size,
    $decimalPlaces = 0, $fillCharacter = ''): string
  {
    $mask = $this->toUpper($mask);
    $value = $this->toUpper($this->normalizeChars($value));
    if (in_array($mask, [ '9', 9, 'N', '9L', 'NL' ])) {
      // Máscara de números
      if ($mask == '9L' || $mask == 'NL') {
        // Obtemos somente os números do valor
        $value = $this->getOnlyNumbers($value);
      }

      $left = '';
      $fillCharacter = 0;
      $type = 's';

      // Formatamos o valor
      $value = ($decimalPlaces > 0)
        ? sprintf("%.{$decimalPlaces}f", $value)
        : $value
      ;

      // Retiramos sinais de pontuação
      $value = str_replace(array(',', '.'), '', $value);
    } elseif (in_array($mask, array('A', 'X'))) {
      // Máscara de letras
      $left = '-';
      $type = 's';
    } else {
      throw new InvalidArgumentException('A máscara é inválida');
    }

    return sprintf("%{$left}{$fillCharacter}{$size}{$type}", mb_substr($value, 0, $size));
  }

  /**
   * Obtém os digitos numéricos de um valor.
   *
   * @param string $value
   *
   * @return string
   */
  protected function getOnlyNumbers(string $value): string
  {
    return preg_replace('/[^[:digit:]]/', '', $value);
  }
}
