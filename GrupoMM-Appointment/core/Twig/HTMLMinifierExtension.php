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
 * Classe responsável por extender o Twig permitindo reduzir o tamanho
 * de códigos HTML, eliminando comentários e espaços desnecessários.
 */

namespace Core\Twig;

use Core\Minifier\HTMLMinifier;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HTMLMinifierExtension
  extends AbstractExtension
{
  /**
   * A flag indicativa de que a extensão está habilitada
   *
   * @var bool
   */
  private $enabled;

  /**
   * A configuração
   *
   * @var array
   */
  private $config;

  /**
   * O minificador
   *
   * @var Minifier
   */
  private $minifier;

  /**
   * O construtor de nossa extensão para minificar códigos HTML.
   * 
   * @param array $config
   *   As opções de configuração.
   */
  public function __construct(array $config = [])
  {
    $this->config = $config;
    $this->minifier = new HTMLMinifier($config);
    $this->enabled = (bool) $this->config['enabled'];
  }

  /**
   * O método que adiciona nossa função de minificar.
   * 
   * @return array
   */
  public function getFilters()
  {
    return [
      new TwigFilter('htmlminify',
        [$this, 'minify'],
        ['is_safe' => ['html']]
      )
    ];
  }

  /**
   * A função de minificar.
   * 
   * @return string
   *   O conteúdo minificado.
   */
  public function minify($content): string
  {
    return ($this->enabled)
      ? $this->minifier->minify($content)
      : $content
    ;
  }
}
