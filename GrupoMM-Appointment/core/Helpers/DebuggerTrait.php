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
 * Essa é uma trait (característica) simples de formatação de parâmetros
 * para depuração que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

use Carbon\Carbon;

trait DebuggerTrait
{
  protected $lineStyle = '""';
  protected $keyStyle = '"line-height: 1.4em;"';
  protected $valueStyle = '"line-height: 1.4em; font-style: italic; '
    . 'color: #53676c"'
  ;
  protected $indentedValueStyle = '"padding-left: 15px; '
    . 'display: block; position: relative;"'
  ;

  /**
   * A flag indicativa de imprimir depuração.
   * 
   * @var boolean
   */
  protected $debug = false;

  /**
   * Mostra uma representação estruturada sobre uma ou mais expressões.
   * Arrays e objetos são explorados recursivamente com valores
   * identados na estrutura mostrada.
   *
   * @param mixed $messages
   *   Um ou mais valores a serem representados
   */
  protected function out(...$messages): void
  {
    if (!$this->debug) {
      return;
    }

    $msgs = '';
    foreach ($messages as $msg) {
      $msgs .= $this->writeValue($msg);
    }

    print "<p style={$this->lineStyle}>{$msgs}</p>";
  }

  /**
   * Mostra apenas o conteúdo se a depuração estiver ativa.
   *
   * @param string $message
   *   A mensagem a ser exibida
   */
  protected function simpleOut(string $message): void
  {
    if (!$this->debug) {
      return;
    }

    print $message;
  }

  /**
   * Converte um valor em uma string.
   *
   * @param mixed $value
   *   O valor a ser convertido
   *
   * @return string
   *   O valor representado na forma textual
   */
  protected function writeValue($value): string
  {
    $result = '';

    if (is_null($value)) {
      $result = '&lt;NULL&gt;';
    } elseif (is_array($value)) {
      $result = $this->writeArray($value);
    } elseif ($value instanceof Carbon) {
      $result = $value->format('d/m/Y H:i:s');
    } elseif (is_bool($value)) {
      $result = ($value)
        ? 'true'
        : 'false'
      ;
    } elseif (is_string($value)) {
      if (empty($value)) {
        $result = "&lt;VAZIO&gt;";
      } else {
        $result = $value;
      }
    } else {
      $result = $value;
    }

    return $result;
  }


  /**
   * Converte uma matriz para uma representação em string
   *
   * @param array $array
   *   A matriz que desejamos converter para string
   *
   * @return string
   */
  protected function writeArray(array $array): string
  {
    $result = '';

    if (count($array) > 0) {
      foreach ($array as $key => $content) {
        if (is_array($content)) {
          $result .= ""
            . "<span style={$this->keyStyle}>"
            .   $key . ": "
            . "</span>[<br>"
            . "<span style={$this->indentedValueStyle}>"
            .   $this->writeArray($content)
            . "</span>]"
          ;
        } else {
          $result .= ""
            . "<span style={$this->keyStyle}>"
            .   $key . ": "
            . "</span>"
            . "<span style={$this->valueStyle}>"
            .   $this->writeValue($content)
            . "</span>"
          ;
        }
        if ($key !== array_key_last($array)) {
          $result .= ",";
        }
        $result .= "<br>";
      }
    } else {
      $result = " ";
    }

    return $result;
  }
}