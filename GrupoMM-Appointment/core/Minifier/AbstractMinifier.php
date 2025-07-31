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
 * Uma classe abstrata para servir como base para redutores de código.
 */

namespace Core\Minifier;

use Exception;
use RuntimeException;

abstract class AbstractMinifier
{
  /**
   * Contém as opções para o processo corrente de minificação
   *
   * @var array
   */
  protected $options = [];

  /**
   * Contém as opções padrão para minificação. Essa matriz é mesclada
   * com àquela transmitida pelo usuário, criando o conjunto final de
   * opções que será armazenado no atributo $options
   *
   * @var array
   */
  protected $defaultOptions = [
    'debug' => false,
    'flaggedComments' => true
  ];

  /**
   * A flag que determina se a depuração está habilitada.
   * 
   * @var boolean
   */
  protected $enableDebug = false;

  /**
   * O construtor de nosso minificador.
   * 
   * @param array $options
   *   As opções de configuração em uma matriz associativa
   */
  public function __construct(array $options = [])
  {
    // Mesca as configurações do usuário com as configurações padrão
    $this->options = array_merge($this->defaultOptions, $options);

    if (array_key_exists('debug', $this->options)) {
      $this->enableDebug = $this->options['debug'];
    }
  }

  /**
   * Analisa um conteúdo fornecido, removendo os caracteres
   * desnecessários para reduzir o conteúdo sem alterar sua
   * funcionalidade.
   *
   * @param string $source
   *   O conteúdo a ser reduzido
   *
   * @return string
   *   O conteúdo reduzido (minificado)
   * 
   * @throws Exception
   *   Em caso de erro na redução
   */
  abstract public function minify(string $source);

  /**
   * Imprime códigos de depuração.
   *
   * @param string $message
   *   A mensagem a ser impressa
   */
  protected function debug($source)
  {
    if ($this->enableDebug) {
      print "{$source}\n";
    }
  }

  /**
   * Verifica se uma string contém o valor desejado.
   * 
   * @param string $needle
   *   O string que estamos desejando procurar
   * @param string $haystack
   *   O string que desejamos analisar
   * 
   * @return bool
   *   Se o string contém o que estamos procurando
   */
  protected function contains(string $needle, string $haystack): bool
  {
    return strpos($haystack, $needle) !== false;
  }
}
