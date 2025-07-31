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
 * Classe responsável por extender o Twig permitindo a inclusão da
 * função 'ToWords' que permite transcrever um valor para a sua
 * representação por extenso, quando necessário.
 */

namespace Core\Twig;

use Core\Helpers\ToWords;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ToWordsExtension
  extends AbstractExtension
{
  /**
   * A classe responsável por transcrever os valores.
   *
   * @var ToWords
   */
  protected $toWords;
  
  /**
   * O construtor de nossa extensão.
   */
  public function __construct()
  {
    $this->toWords = new ToWords();
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('intToWords', [$this, 'intValue']),
      new TwigFunction('percentageToWords', [$this, 'percentageValue']),
      new TwigFunction('monetaryToWords', [$this, 'monetaryValue'])
    ];
  }
  
  /**
   * Transcreve um valor inteiro para a sua representação por extenso.
   * 
   * @param int $value
   *   O valor a ser transcrito para a sua representação por extenso
   * 
   * @return string
   *   O valor por extenso
   */
  public function intValue(int $value): string
  {
    return $this->toWords->intValue($value);
  }
  
  /**
   * Transcreve um valor de percentagem para a sua representação por
   * extenso.
   * 
   * @param float $value
   *   O valor a ser transcrito para a sua representação por extenso
   * @param int $decimals (opcional)
   *   A quantidade de casas decimais a serem consideradas (o padrão é
   *   2 casas decimais)
   * 
   * @return string
   *   O valor por extenso
   */
  public function percentageValue(float $value, int $decimals = 2): string
  {
    return $this->toWords->percentageValue($value, $decimals);
  }
  
  /**
   * Transcreve um valor monetary para a sua representação por extenso.
   * 
   * @param float $value
   *   O valor a ser transcrito para a sua representação por extenso
   * @param string $currency (opcional)
   *   A moeda em que o valor deve ser representado (padrão = 'BRL')
   * 
   * @return string
   *   O valor por extenso
   */
  public function monetaryValue(int $value,
    string $currency = 'BRL'): string
  {
    return $this->toWords->monetaryValue($value, $currency);
  }
}
