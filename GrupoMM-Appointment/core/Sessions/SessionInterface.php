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
 * A interface para os métodos exportados pelo serviço de sessão.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Sessions;

interface SessionInterface
{
  /**
   * Recupera um valor armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * @param mixed $default
   *   O valor padrão a ser devolvido caso a chave não possua um valor
   * atribuído
   * 
   * @return mixed
   *   O valor armazenado
   */
  public function get(string $name, $default = null);
  
  /**
   * Armazena um valor na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * @param mixed $value
   *   O valor a ser armazenado
   *
   * @return void
   */
  public function set(string $name, $value);
  
  /**
   * Verifica se um valor foi armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * 
   * @return boolean
   *   O indicativo de que a chave corresponde à um valor armazenado
   */
  public function has(string $name): bool;
  
  /**
   * Remove um valor da sessão
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   *
   * @return void
   */
  public function delete(string $name);

  /**
   * Remove todos os valores armazenados na sessão.
   *
   * @return void
   */
  public function clear();
}
