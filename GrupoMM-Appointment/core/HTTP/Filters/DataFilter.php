<?php
/*
 * This file is part of Extension Library.
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
 * A interface para um sistema que permita iterar (percorrer) os dados
 * de filtragem num serviço.
 */

namespace Core\HTTP\Filters;

use Countable;
use Iterator;

interface DataFilter
  extends Iterator, Countable
{
  /**
   * Este método retorna a quantidade de itens total.
   * 
   * @return int
   *   A quantidade de itens
   */
  public function total(): int;

  /**
   * Obtém os nomes dos parâmetros a serem utilizados como filtro nas
   * requisições, cujo valor será obtido à partir dos dados armazenados.
   * 
   * @return array
   *   Uma matriz com os parâmetros de filtragem
   */
  public function getFilterParameters(): array;

  /**
   * Este método retorna o valor do parâmetro a ser utilizado como
   * filtro na requisição.
   * 
   * @param string $name
   *   O nome do parâmetro para o qual se deseja obter o respectivo
   *   valor nesta iteração
   * 
   * @return mixed|null
   *   O valor do parâmetro de filtragem
   */
  public function parameterValue(string $name);
 }
