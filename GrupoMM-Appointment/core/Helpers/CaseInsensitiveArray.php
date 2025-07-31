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
 * Essa é uma classe que permite criar uma matriz cujos índices são
 * insensíveis a maiúsculas e minúsculas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

class CaseInsensitiveArray
  implements \ArrayAccess, \Countable, \Iterator
{
  /**
   * @var mixed[] Armazenamento de dados com os índices em minúsculo.
   * @see offsetSet()
   * @see offsetExists()
   * @see offsetUnset()
   * @see offsetGet()
   * @see count()
   * @see current()
   * @see next()
   * @see key()
   */
  private $data = [];

  /**
   * @var string[] Os índices sensíveis a maiúsculas e minúsculas.
   * @see offsetSet()
   * @see offsetUnset()
   * @see key()
   */
  private $keys = [];

  /**
   * O construtor da nossa matriz. Permite a criação de uma matriz vazia
   * ou a conversão de uma matriz existente em uma matriz que não faça a
   * distinção entre maiúsculas e minúsculas em suas chaves.
   * 
   * Cuidado: Dados podem ser perdidos ao se converter matrizes que
   * diferenciam maiúsculas de minúsculas em seus índices em matrizes
   * que não façam esta distinção em seus índices (chaves).
   *
   * @param mixed[] $initial (opcional)
   *   Uma matriz existente para converter.
   *
   * @return CaseInsensitiveArray
   *   A matriz insensível a maiúsculas e minúsculas em seus índices
   */
  public function __construct(array $initial = null)
  {
    if ($initial !== null) {
      foreach ($initial as $key => $value) {
        $this->offsetSet($key, $value);
      }
    }
  }

  /**
   * Defina os dados em um deslocamento especificado. Internamente
   * converte o deslocamento em minúsculas e armazena o deslocamento com
   * distinção entre maiúsculas e minúsculas e os dados nos índices em
   * minúsculas em $this->keys e @this->data respectivamente.
   *
   * @see https://secure.php.net/manual/en/arrayaccess.offsetset.php
   *
   * @param string $offset
   *   O deslocamento no qual armazenar os dados (não faz distinção
   *   entre maiúsculas e minúsculas)
   * @param mixed $value
   *   Os dados a serem armazenados no deslocamento especificado.
   */
  public function offsetSet($offset, $value)
  {
    if ($offset === null) {
      $this->data[] = $value;
    } else {
      $offsetLower = strtolower($offset);
      $this->data[$offsetLower] = $value;
      $this->keys[$offsetLower] = $offset;
    }
  }

  /**
   * Verifica se o deslocamento existe no armazenamento de dados.
   * Internamente o índice é pesquisado com a versão em minúsculas do
   * deslocamento fornecido.
   *
   * @see https://secure.php.net/manual/en/arrayaccess.offsetexists.php
   *
   * @param string $offset
   *   O deslocamento a ser verificado
   *
   * @return bool
   *   Se o deslocamento existe
   */
  public function offsetExists($offset)
  {
    return (bool) array_key_exists(strtolower($offset), $this->data);
  }

  /**
   * Desfaz o deslocamento especificado. Internamente converte o
   * deslocamento fornecido em minúsculas e desfaz a configuração do
   * índice com distinção entre maiúsculas e minúsculas, bem como os
   * dados armazenados.
   *
   * @see https://secure.php.net/manual/en/arrayaccess.offsetunset.php
   *
   * @param string $offset
   *   O deslocamento a ser desfeito
   */
  public function offsetUnset($offset)
  {
    $offsetLower = strtolower($offset);
    unset($this->data[$offsetLower]);
    unset($this->keys[$offsetLower]);
  }

  /**
   * Retorne os dados armazenados no deslocamento fornecido.
   * Internamente o deslocamento é convertido em minúsculas e a pesquisa
   * é feita diretamente no armazenamento de dados.
   *
   * @see https://secure.php.net/manual/en/arrayaccess.offsetget.php
   *
   * @param string $offset
   *   O deslocamento a ser pesquisado
   *
   * @return mixed
   *   Os dados armazenados no deslocamento
   */
  public function offsetGet($offset)
  {
    $offsetLower = strtolower($offset);

    return isset($this->data[$offsetLower]) ? $this->data[$offsetLower] : null;
  }

  /**
   * Obtém a quantidade de elementos armazenados na matriz.
   *
   * @see https://secure.php.net/manual/en/countable.count.php
   *
   * @param void
   *
   * @return int
   *   O número de elementos armazenados na matriz
   */
  public function count()
  {
    return (int) count($this->data);
  }

  /**
   * Obtém o elemento atual na matriz.
   *
   * @see https://secure.php.net/manual/en/iterator.current.php
   *
   * @param void
   *
   * @return mixed
   *   Os dados na posição atual
   */
  public function current()
  {
    return current($this->data);
  }

  /**
   * Avança para o próximo elemento na matriz.
   *
   * @see https://secure.php.net/manual/en/iterator.next.php
   *
   * @param void
   *
   * @return void
   */
  public function next()
  {
    next($this->data);
  }

  /**
   * Obtém a chave com distinção entre maiúsculas e minúsculas na
   * posição atual.
   *
   * @see https://secure.php.net/manual/en/iterator.key.php
   *
   * @param void
   *
   * @return mixed
   *   A chave com distinção entre maiúsculas e minúsculas na posição
   *   atual
   */
  public function key()
  {
    $key = key($this->data);

    return isset($this->keys[$key]) ? $this->keys[$key] : $key;
  }

  /**
   * Determina se a posição atual é válida.
   *
   * @see https://secure.php.net/manual/en/iterator.valid.php
   *
   * @return bool
   */
  public function valid()
  {
    return (bool) (key($this->data) !== null);
  }

  /**
   * Retrocede a matriz para o começo da mesma.
   *
   * @see https://secure.php.net/manual/en/iterator.rewind.php
   *
   * @param void
   *
   * @return void
   */
  public function rewind()
  {
    reset($this->data);
  }
}