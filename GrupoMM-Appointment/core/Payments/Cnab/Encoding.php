<?php

/**
 * Copyright (c) 2008 Sebastián Grignoli
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 * 3. Neither the name of copyright holders nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL COPYRIGHT
 * HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF
 * THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */

/**
 * @author   "Sebastián Grignoli" <grignoli@gmail.com>
 * @package  Encoding
 * @version  2.0
 * @link     https://github.com/neitanod/forceutf8
 * @example  https://github.com/neitanod/forceutf8
 * @license  Revised BSD
 */

namespace Core\Payments\Cnab;

class Encoding {
  /**
   * As opções de codificação e decodificação
   */
  const ICONV_TRANSLIT = "TRANSLIT";
  const ICONV_IGNORE = "IGNORE";
  const WITHOUT_ICONV = "";

  /**
   * A tabela de conversão da codificação Windows-1252 para UTF8.
   *
   * @var array
   */
  protected static $win1252ToUtf8 = [
    128 => "\xe2\x82\xac",

    130 => "\xe2\x80\x9a",
    131 => "\xc6\x92",
    132 => "\xe2\x80\x9e",
    133 => "\xe2\x80\xa6",
    134 => "\xe2\x80\xa0",
    135 => "\xe2\x80\xa1",
    136 => "\xcb\x86",
    137 => "\xe2\x80\xb0",
    138 => "\xc5\xa0",
    139 => "\xe2\x80\xb9",
    140 => "\xc5\x92",

    142 => "\xc5\xbd",


    145 => "\xe2\x80\x98",
    146 => "\xe2\x80\x99",
    147 => "\xe2\x80\x9c",
    148 => "\xe2\x80\x9d",
    149 => "\xe2\x80\xa2",
    150 => "\xe2\x80\x93",
    151 => "\xe2\x80\x94",
    152 => "\xcb\x9c",
    153 => "\xe2\x84\xa2",
    154 => "\xc5\xa1",
    155 => "\xe2\x80\xba",
    156 => "\xc5\x93",

    158 => "\xc5\xbe",
    159 => "\xc5\xb8"
  ];

  /**
   * A tabela de conversão de um UTF-8 quebrado para UTF-8.
   *
   * @var array
   */
  protected static $brokenUtf8ToUtf8 = [
    "\xc2\x80" => "\xe2\x82\xac",

    "\xc2\x82" => "\xe2\x80\x9a",
    "\xc2\x83" => "\xc6\x92",
    "\xc2\x84" => "\xe2\x80\x9e",
    "\xc2\x85" => "\xe2\x80\xa6",
    "\xc2\x86" => "\xe2\x80\xa0",
    "\xc2\x87" => "\xe2\x80\xa1",
    "\xc2\x88" => "\xcb\x86",
    "\xc2\x89" => "\xe2\x80\xb0",
    "\xc2\x8a" => "\xc5\xa0",
    "\xc2\x8b" => "\xe2\x80\xb9",
    "\xc2\x8c" => "\xc5\x92",

    "\xc2\x8e" => "\xc5\xbd",


    "\xc2\x91" => "\xe2\x80\x98",
    "\xc2\x92" => "\xe2\x80\x99",
    "\xc2\x93" => "\xe2\x80\x9c",
    "\xc2\x94" => "\xe2\x80\x9d",
    "\xc2\x95" => "\xe2\x80\xa2",
    "\xc2\x96" => "\xe2\x80\x93",
    "\xc2\x97" => "\xe2\x80\x94",
    "\xc2\x98" => "\xcb\x9c",
    "\xc2\x99" => "\xe2\x84\xa2",
    "\xc2\x9a" => "\xc5\xa1",
    "\xc2\x9b" => "\xe2\x80\xba",
    "\xc2\x9c" => "\xc5\x93",

    "\xc2\x9e" => "\xc5\xbe",
    "\xc2\x9f" => "\xc5\xb8"
  ];

  /**
   * A tabela de conversão de UTF-8 para Windows-1252.
   *
   * @var array
   */
  protected static $utf8ToWin1252 = [
    "\xe2\x82\xac" => "\x80",

    "\xe2\x80\x9a" => "\x82",
    "\xc6\x92"     => "\x83",
    "\xe2\x80\x9e" => "\x84",
    "\xe2\x80\xa6" => "\x85",
    "\xe2\x80\xa0" => "\x86",
    "\xe2\x80\xa1" => "\x87",
    "\xcb\x86"     => "\x88",
    "\xe2\x80\xb0" => "\x89",
    "\xc5\xa0"     => "\x8a",
    "\xe2\x80\xb9" => "\x8b",
    "\xc5\x92"     => "\x8c",

    "\xc5\xbd"     => "\x8e",


    "\xe2\x80\x98" => "\x91",
    "\xe2\x80\x99" => "\x92",
    "\xe2\x80\x9c" => "\x93",
    "\xe2\x80\x9d" => "\x94",
    "\xe2\x80\xa2" => "\x95",
    "\xe2\x80\x93" => "\x96",
    "\xe2\x80\x94" => "\x97",
    "\xcb\x9c"     => "\x98",
    "\xe2\x84\xa2" => "\x99",
    "\xc5\xa1"     => "\x9a",
    "\xe2\x80\xba" => "\x9b",
    "\xc5\x93"     => "\x9c",

    "\xc5\xbe"     => "\x9e",
    "\xc5\xb8"     => "\x9f"
  ];

  /**
   * Esta função deixa os caracteres UTF8 sozinhos, enquanto converte
   * quase todos os não UTF8 em UTF8. Ela pressupõe que a codificação da
   * string original seja Windows-1252 ou ISO 8859-1. Pode falhar ao
   * converter caracteres para UTF-8 se eles se enquadrarem em um destes
   * cenários:
   *
   * 1) quando qualquer um desses caracteres:
   *    ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
   *    são seguidos por qualquer um desses:  ("grupo B")
   *    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
   * 
   * Por exemplo:   %ABREPRESENT%C9%BB. «REPRESENTÉ»
   *   O caracter "«" (%AB) será convertido, mas o "É" seguido por "»"
   *   (%C9%BB) também é um caractere unicode válido e não será
   *   alterado.
   *
   * 2) quando qualquer um desses caracteres:
   *    àáâãäåæçèéêëìíîï
   *    são seguidos por DOIS caracteres do grupo B,
   * 
   * 3) quando algum desses caracteres:
   *    ðñòó
   *    são seguidos por TRÊS caracteres do grupo B.
   *
   * @param string $text
   *   O conteúdo a ser convertido
   * @return string
   *   A mesma string, codificada em UTF8
   */
  static function toUTF8(string $text): string
  {
    if (is_array($text)) {
      foreach($text as $k => $v) {
        $text[$k] = self::toUTF8($v);
      }

      return $text;
    }

    if (!is_string($text)) {
      return $text;
    }

    $max = self::strlen($text);

    $buf = "";
    for ($i = 0; $i < $max; $i++) {
      $c1 = $text[$i];

      if ($c1>="\xc0") {
        // Deve ser convertido para UTF8, se já não for UTF8
        $c2 = $i+1 >= $max? "\x00" : $text[ $i+1 ];
        $c3 = $i+2 >= $max? "\x00" : $text[ $i+2 ];
        $c4 = $i+3 >= $max? "\x00" : $text[ $i+3 ];

        if ($c1 >= "\xc0" & $c1 <= "\xdf") {
          // Parece 2 bytes UTF8
          if ($c2 >= "\x80" && $c2 <= "\xbf") {
            // Sim, quase certo que já é UTF8
            $buf .= $c1 . $c2;
            $i++;
          } else {
            // Não é um UTF-8 válido. Converta-o.
            $cc1 = (chr(ord($c1) / 64) | "\xc0");
            $cc2 = ($c1 & "\x3f") | "\x80";
            $buf .= $cc1 . $cc2;
          }
        } elseif ($c1 >= "\xe0" & $c1 <= "\xef") {
          // Parece 3 bytes UTF8
          if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf") {
            // Sim, quase certo que já é UTF8
            $buf .= $c1 . $c2 . $c3;
            $i = $i + 2;
          } else {
            // Não é um UTF-8 válido. Converta-o.
            $cc1 = (chr(ord($c1) / 64) | "\xc0");
            $cc2 = ($c1 & "\x3f") | "\x80";
            $buf .= $cc1 . $cc2;
          }
        } elseif ($c1 >= "\xf0" & $c1 <= "\xf7") {
          // Parece 4 bytes UTF8
          if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf") {
            // Sim, quase certo que já é UTF8
            $buf .= $c1 . $c2 . $c3 . $c4;
            $i = $i + 3;
          } else {
            // Não é um UTF-8 válido. Converta-o.
            $cc1 = (chr(ord($c1) / 64) | "\xc0");
            $cc2 = ($c1 & "\x3f") | "\x80";
            $buf .= $cc1 . $cc2;
          }
        } else {
          // Não se parece com UTF8, mas deve ser convertido
          $cc1 = (chr(ord($c1) / 64) | "\xc0");
          $cc2 = (($c1 & "\x3f") | "\x80");
          $buf .= $cc1 . $cc2;
        }
      } elseif (($c1 & "\xc0") === "\x80") {
        // Precisa de conversão
        if (isset(self::$win1252ToUtf8[ord($c1)])) {
          // Encontrado em casos especiais do Windows-1252
          $buf .= self::$win1252ToUtf8[ord($c1)];
        } else {
          $cc1 = (chr(ord($c1) / 64) | "\xc0");
          $cc2 = (($c1 & "\x3f") | "\x80");
          $buf .= $cc1 . $cc2;
        }
      } else {
        // Não precisa de conversão
        $buf .= $c1;
      }
    }

    return $buf;
  }

  /**
   * Converte o texto de codificação UTF-8 para Windows-1252.
   *
   * @param string $text
   *   O texto a ser convertido
   * @param strin $option
   *   As opções de conversão
   *
   * @return string
   */
  static function toWin1252(
    string $text,
    string $option = self::WITHOUT_ICONV
  ): string
  {
    if (is_array($text)) {
      foreach($text as $k => $v) {
        $text[$k] = self::toWin1252($v, $option);
      }

      return $text;
    } elseif(is_string($text)) {
      return static::utf8_decode($text, $option);
    } else {
      return $text;
    }
  }

  /**
   * Converte o texto de codificação UTF-8 para ISO-8859.
   *
   * @param string $text
   *   O texto a ser convertido
   *
   * @return string
   */
  static function toISO8859(string $text): string
  {
    return self::toWin1252($text);
  }

  /**
   * Converte o texto de codificação UTF-8 para Latin-1.
   *
   * @param string $text
   *   O texto a ser convertido
   *
   * @return string
   */
  static function toLatin1(string $text = self::WITHOUT_ICONV): string
  {
    return self::toWin1252($text);
  }

  /**
   * Corrige a conversão de codificação do texto para UTF-8.
   *
   * @param string $text
   *   O texto a ser corrigido
   * @param strin $option
   *   As opções de conversão
   *
   * @return string
   */
  static function fixUTF8(
    string $text,
    string $option = self::WITHOUT_ICONV
  ): string
  {
    if (is_array($text)) {
      foreach($text as $k => $v) {
        $text[$k] = self::fixUTF8($v, $option);
      }

      return $text;
    }

    if(!is_string($text)) {
      return $text;
    }

    $last = "";
    while($last <> $text) {
      $last = $text;
      $text = self::toUTF8(static::utf8_decode($text, $option));
    }
    $text = self::toUTF8(static::utf8_decode($text, $option));

    return $text;
  }

  /**
   * Se você recebeu uma string UTF-8 que foi convertida do Windows-1252
   * como ISO8859-1 (ignorando os caracteres do Windows-1252 de 80 a
   * 9F), use esta função para corrigi-la.
   *
   * @param string $text
   *   O texto a ser convertido
   *
   * @return string
   */
  static function UTF8FixWin1252Chars(string $text): string
  {
    return str_replace(
      array_keys(self::$brokenUtf8ToUtf8),
      array_values(self::$brokenUtf8ToUtf8),
      $text
    );
  }

  static function removeBOM(string $str=""): string
  {
    if (substr($str, 0,3) === pack("CCC",0xef,0xbb,0xbf)) {
      $str=substr($str, 3);
    }

    return $str;
  }

  /**
   * Obtém o comprimento de uma string.
   *
   * @param string $text
   *   A string a ser analisada
   *
   * @return int
   */
  protected static function strlen(string $text): int
  {
    return (function_exists('mb_strlen') && ((int) ini_get('mbstring.func_overload')) & 2)
      ? mb_strlen($text,'8bit')
      : strlen($text)
    ;
  }

  /**
   * Normaliza a codificação de um texto.
   *
   * @param string $encodingLabel
   *   O texto a ser convertido
   *
   * @return string
   */
  public static function normalizeEncoding(
    string $encodingLabel
  ): string
  {
    $encoding = strtoupper($encodingLabel);
    $encoding = preg_replace('/[^a-zA-Z0-9\s]/', '', $encoding);
    $equivalences = array(
      'ISO88591' => 'ISO-8859-1',
      'ISO8859'  => 'ISO-8859-1',
      'ISO'      => 'ISO-8859-1',
      'LATIN1'   => 'ISO-8859-1',
      'LATIN'    => 'ISO-8859-1',
      'UTF8'     => 'UTF-8',
      'UTF'      => 'UTF-8',
      'WIN1252'  => 'ISO-8859-1',
      'WINDOWS1252' => 'ISO-8859-1'
    );

    if(empty($equivalences[$encoding])){
      return 'UTF-8';
    }

    return $equivalences[$encoding];
  }

  /**
   * Codifica uma string.
   *
   * @param string $encodingLabel
   *   O nome da codificação a ser utilizada
   * @param string $text
   *   O valor a ser codificado
   *
   * @return string
   */
  public static function encode(
    string $encodingLabel,
    string $text
  ): string
  {
    $encodingLabel = self::normalizeEncoding($encodingLabel);

    if ($encodingLabel === 'ISO-8859-1') {
      return self::toLatin1($text);
    }

    return self::toUTF8($text);
  }

  /**
   * Decodifica uma string UTF-8.
   *
   * @param string $text
   *   O valor a ser decodificado
   * @param string $option
   *   As opções de codificação
   *
   * @return string
   */
  protected static function utf8_decode(
    string $text,
    string $option = self::WITHOUT_ICONV
  ): string
  {
    if ($option == self::WITHOUT_ICONV || !function_exists('iconv')) {
      $output = utf8_decode(
        str_replace(
          array_keys(self::$utf8ToWin1252),
          array_values(self::$utf8ToWin1252),
          self::toUTF8($text)
        )
      );
    } else {
      $output = iconv(
        "UTF-8", "Windows-1252"
          . ($option === self::ICONV_TRANSLIT
              ? '//TRANSLIT'
              : ($option === self::ICONV_IGNORE
                  ? '//IGNORE'
                  : '')),
        $text
      );
    }

    return $output;
  }
}
