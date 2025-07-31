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
 * Classe responsável por extender o Twig permitindo a inclusão do
 * filtro 'json' que permite a interpretação correta de um campo do
 * tipo json.
 */

namespace Core\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JsonFilter
  extends AbstractExtension
{
  /**
   * O construtor deste filtro.
   */
  public function __construct() { }
  
  /**
   * Recupera os filtros para o sistema Twig.
   * 
   * @return array
   */
  public function getFilters()
  {
    return [
      new TwigFilter('json_decode', [$this, 'jsonFilter']),
    ];
  }
  
  /**
   * Uma função que decodifica um valor do tipo json a partir de um
   * valor.
   * 
   * @param mixed $value
   *   O valor a ser convertido
   * 
   * @return array
   *   A matriz com os dados do Json
   */
  public function jsonFilter($value): array
  {
    if (is_string($value)) {

      return (array) json_decode($value, true);
    } else {
      // Considera o json nulo
      return [];
    }
  }
}
