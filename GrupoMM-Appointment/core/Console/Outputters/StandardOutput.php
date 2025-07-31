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
 * Um impressor de textos na saída padrão.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Console\Outputters;

use Core\Console\Formatters\{Formatter, PlainText};

class StandardOutput
  implements Outputter
{
  /**
   * O formatador de cores em modo console.
   * 
   * @var Core\Console\Formatters\Formatter
   */
  protected $formatter;

  /**
   * O construtor de nosso outputter.
   * 
   * @param Formatter $formatter
   *   Um formatador de textos
   */
  public function __construct(Formatter $formatter = null)
  {
    $this->formatter = ($formatter)
      ? $formatter
      : new PlainText()
    ;
  }

  /**
   * Imprime a string com uma nova linha no final.
   *
   * @param string $values
   *   O valor ou valores a serem impressos
   */
  public function out(string ...$values): void
  {
    $this->inline(...$values);
    $this->inline($this->newLine());
  }

  /**
   * Imprime a string.
   *
   * @param string $values
   *   O valor ou valores a serem impressos
   */
  public function inline(string ...$values): void
  {
    $output = '';
    foreach ( $values as $value ) {
      $output .= $value;
    }

    print $this->formatter->format($output);
  }

  /**
   * Obtém o caractere de nova linha.
   *
   * @return string
   */
  public function newLine(): string
  {
    return PHP_EOL;
  }
}