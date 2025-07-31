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
 * A classe que permita iterar (percorrer) os dados de filtragem durante
 * as requisições aos dados de um serviço externo.
 */

namespace Core\HTTP\Filters;

class DataFilterIterator
  implements DataFilter
{
  /**
   * Uma matriz com uma sequência de pares de chaves => valores, onde a
   * chave representa o nome de um parâmetro de filtragem e o valor
   * representa o campo dentro dos dados de requisição onde obteremos
   * o valor para preencher no campo de filtragem.
   * 
   * @var array
   */
  protected $parameters;

  /**
   * A matriz com os dados de requisição.
   * 
   * @var array
   */
  protected $data;

  /**
   * O índice indicativo da posição dentro dos dados.
   *
   * @var int
   */
  protected $currentIndex = null;

  /**
   * O construtor recebe os dados necessários para compor a requisição
   * utilizando à API ao serviço.
   *
   * @param array $parameters
   *   Uma matriz contendo informações dos parâmetros de filtragem
   *   (chaves) e os respectivos campos no qual obtemos estes valores
   *   dentro da matriz de dados
   * @param array $data
   *   Os dados que serão percorridos para preencher nossas requisições
   */
  public function __construct(array $parameters = [], array $data = [])
  {
    $this->parameters = $parameters;

    if (count($data) === 0) {
      // Criamos uma matriz com um elemento para permitir ao menos uma
      // iteração do loop de requisições
      $data[] = [ 'All' ];
    }
    $this->data = $data;

    // Retorna ao início
    $this->rewind();
  }

  /**
   * Este método redefine o ponteiro dos nossos dados para o início.
   */
  public function rewind(): void
  {
    // Retorna ao início
    $this->currentIndex = 0;
  }

  /**
   * Este método retorna a linha atual como uma matriz bidimensional.
   *
   * @return array
   *   A linha atual como uma matriz bidimensional.
   */
  public function current(): array
  {
    return $this->data[ $this->currentIndex ];
  }

  /**
   * Este método retorna o número da linha atual.
   *
   * @return int
   *   O número da linha atual
   */
  public function key(): int
  {
    return $this->currentIndex;
  }

  /**
   * Este método verifica se o final dos dados foi atingido.
   *
   * @return bool
   *   Retorna verdadeiro se o fim foi alcançado, falso caso contrário
   */
  public function next(): bool
  {
    $this->currentIndex++;

    return $this->valid();
  }

  /**
   * Verifica se o próximo item é válido.
   *
   * @return bool
   */
  public function valid(): bool
  {
    return ($this->currentIndex < count($this->data));
  }

  /**
   * Recupera a quantidade de itens totais.
   * 
   * @return int
   *   A quantidade de itens totais
   */
  public function total(): int
  {
    return count($this->data);
  }

  /**
   * Recupera a quantidade de itens totais.
   * 
   * @return int
   *   A quantidade de itens totais
   */
  public function count(): int
  {
    return count($this->data);
  }

  /**
   * Obtém os nomes dos parâmetros a serem utilizados como filtro nas
   * requisições, cujo valor será obtido à partir dos dados armazenados.
   * 
   * @return array
   *   Uma matriz com os parâmetros de filtragem
   */
  public function getFilterParameters(): array
  {
    return array_keys($this->parameters);
  }

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
  public function parameterValue(string $name)
  {
    // Recuperamos os dados correntes
    $data = $this->current();

    if (array_key_exists($name, $this->parameters)) {
      $field = $this->parameters[$name];
      
      if (array_key_exists($field, $data)) {
        return $data[$field];
      }
    }

    return null;
  }
}
