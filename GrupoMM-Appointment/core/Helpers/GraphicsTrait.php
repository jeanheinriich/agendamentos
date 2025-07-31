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
 * Essa é uma trait (característica) simples de formatação de textos que
 * limita o seu tamanho em função de uma dimensão (em pixels) e do tipo
 * de fonte utilizada que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

trait GraphicsTrait
{
  /**
   * Quebra uma string baseado num determinado número de pixels.
   *
   * Esta função opera de maneira semelhante à função wordwrap nativa do
   * PHP. No entanto, ela calcula a quebra do texto com base na fonte e
   * no tamanho do pixel, em vez da contagem de caracteres. Isso pode
   * gerar quebras de texto mais uniformes para as frases com um número
   * considerável de caracteres.
   * 
   * @param string $text
   *   O texto de entrada
   * @param int $width
   *   A largura, em pixels, da área onde o texto será quebrado
   * @param int $size
   *   O tamanho da fonte, expresso em pixels
   * @param string $font
   *   O caminho para a fonte com a qual iremos realizar as medições do
   * texto
   * 
   * @return string
   *   A string original com caracteres de quebra de linha inseridos
   * manualmente nos pontos de quebra detectados
   */
  protected function wordwrap(string $text, int $width, int $size,
    string $font)
  {
    // Ignoramos valores em branco
    if (!$text) {
      return $text;
    }

    // Verifica se imagettfbbox espera que o tamanho da fonte seja
    // declarado em pontos ou em pixels
    $mult = version_compare(GD_VERSION, '2.0', '>=')
      ? .75
      : 1
    ;
    $font = __DIR__ . '/../../vendor/mpdf/mpdf/ttfonts/' . $font . '.ttf';

    // Verifica se o texto já cabe no espaço designado sem a necessidade
    // de quebra automática
    $box = imagettfbbox($size * $mult, 0, $font, $text);
    if ($box[2] - $box[0] / $mult < $width) {
      return $text;
    }

    // Começamos medindo cada linha de nossa entrada e injeta quebras de
    // linha quando o estouro for detectado
    $output = '';
    $length = 0;

    // Separamos nosso texto em palavras
    $words = preg_split("/[\s]+/u", $text);
    $word_count = count($words);

    for ($i = 0; $i < $word_count; ++$i) {
      // Se a próxima palavra já for uma quebra de linha, retorna ao
      // começo
      if (PHP_EOL === $words[$i]) {
        $length = 0;
      }

      // Retiramos todas as tabulações
      if (!$length) {
        $words[$i] = preg_replace('/^\t+/', '', $words[$i]);
      }

      $box = imagettfbbox($size * $mult, 0, $font, $words[$i]);
      $m   = $box[2] - $box[0] / $mult;

      // Esta é uma palavra longa, então tentâmos hifenizá-la
      if (($diff = $width - $m) <= 0) {
        $diff = abs($diff);

        // Descubra de qual extremidade da palavra começar a medição.
        // Economiza alguns ciclos extras em uma função já pesada
        if ($diff - $width <= 0) {
          for($s = strlen($words[$i]); $s; --$s) {
            $box = imagettfbbox($size * $mult, 0, $font,
              substr($words[$i], 0, $s) . '-')
            ;

            if ($width > ($box[2] - $box[0] / $mult) + $size) {
              $breakpoint = $s;
              break;
            }
          }
        } else {
          $word_length = strlen($words[$i]);

          for ($s = 0; $s < $word_length; ++$s) {
            $box = imagettfbbox($size * $mult, 0, $font,
              substr($words[$i], 0, $s+1) . '-')
            ;

            if ($width < ($box[2] - $box[0] / $mult) + $size) {
              $breakpoint = $s;
              break;
            }
          }
        }

        if ($breakpoint) {
          $w_l = substr($words[$i], 0, $s+1) . '-';
          $w_r = substr($words[$i], $s+1);

          $words[$i] = $w_l;
          array_splice($words, $i+1, 0, $w_r);
          ++$word_count;
          $box = imagettfbbox($size * $mult, 0, $font, $w_l);
          $m   = $box[2] - $box[0] / $mult;
        }
      }

      // Se não houver mais espaço na linha atual para caber a próxima
      // palavra, interrompa e inicie uma nova linha
      if ($length > 0 && $length + $m >= $width) {
        $output .= PHP_EOL;
        $length  = 0;
      }

      // Escrevemos outra palavra e aumentamos o comprimento total da
      // linha atual
      $output .= $words[$i] . ' ';
      $length += $m + 1;
    }

    return $output;
  }

  /**
   * Limita o comprimento de uma string baseado num determinado número
   * de pixels.
   * 
   * @param string $text
   *   O texto de entrada
   * @param int $width
   *   A largura, em pixels, da área onde o texto será quebrado
   * @param int $size
   *   O tamanho da fonte, expresso em pixels
   * @param string $font
   *   O caminho para a fonte com a qual iremos realizar as medições do
   * texto
   * 
   * @return string
   *   A string original com caracteres limitados ao comprimento do
   * texto visível no espaço informado
   */
  protected function limitWidth(string $text, int $width, int $size,
    string $font)
  {
    // Ignoramos valores em branco
    if (!$text) {
      return $text;
    }

    // Verifica se imagettfbbox espera que o tamanho da fonte seja
    // declarado em pontos ou em pixels
    $mult = version_compare(GD_VERSION, '2.0', '>=')
      ? .75
      : 1
    ;
    $font = __DIR__ . '/../../vendor/mpdf/mpdf/ttfonts/' . $font . '.ttf';

    // Verifica se o texto já cabe no espaço designado sem a necessidade
    // de limitar
    $box = imagettfbbox($size * $mult, 0, $font, $text);
    if ($box[2] - $box[0] / $mult < $width) {
      return $text;
    }

    // Retiramos todas as tabulações
    $text = preg_replace('/^\t+/', '', $text);
    $output = '';

    // Vamos reduzindo o texto até que o mesmo "caiba" no espaço
    // desejado
    for ($lineSize = strlen($text); $lineSize; --$lineSize) {
      $content = substr($text, 0, $lineSize) . '…';
      $box = imagettfbbox($size * $mult, 0, $font, $content);

      if ($width > ($box[2] - $box[0] / $mult) + $size) {
        // Encontramos a porção do texto que permite o resultado seja
        // renderizado no espaço desejado
        $output = $content;

        break;
      }
    }

    return $output;
  }
}
