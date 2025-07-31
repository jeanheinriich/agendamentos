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
 * A interface para o model dos dados do usuário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Users;

interface UserInterface
{
  /**
   * Retorna a ID do usuário.
   * 
   * @return int
   *   A id do usuário
   */
  public function getUserId(): int;
  
  /**
   * Retorna o nome de login do usuário (nome do usuário).
   * 
   * @return string
   *   O nome de login do usuário
   */
  public function getUserLogin(): string;
  
  /**
   * Retorna o atributo nome do usuário (nome completo).
   * 
   * @return string
   *   O nome do usuário
   */
  public function getUserName(): string;
  
  /**
   * Retorna o atributo senha do usuário (encriptada).
   * 
   * @return string
   *   A senha do usuário encriptada
   */
  public function getUserPassword(): string;

  /**
   * Armazena os dados do modelo no banco de dados
   *
   * @param  array  $options
   * 
   * @return bool
   */
  public function save(array $options = []);

  /**
   * Retorna o relacionamento com a tabela de entidades.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   *   As informações de entidades
   */
  public function entity();
}
