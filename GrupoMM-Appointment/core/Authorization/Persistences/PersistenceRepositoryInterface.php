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
 * Interface para uma persistência.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Persistences;

interface PersistenceRepositoryInterface
{
  /**
   * Verifica se há um código de persistência na sessão atual
   * 
   * @return mixed|null
   *   Retorna os dados do código de persistência ou nulo se não
   * localizar
   */
  public function getPersistenceCode();
  
  /**
   * Encontra uma persistência pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência contendo o seletor e o validador (uma
   * matriz ou objeto)
   * 
   * @return Persistence
   *   Um objeto de persistência ou falso se não conseguir localizar
   */
  public function findByPersistenceCode($code);
  
  /**
   * Encontra um usuário pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return Core\Authorization\Users\UserInterface
   *   Um objeto com os dados do usuário ou falso se não localizar
   */
  public function findUserByPersistenceCode($code);
  
  /**
   * Adiciona uma nova persistência de usuário à sessão atual e anexa o
   * usuário.
   * 
   * @param PersistableInterface $persistable
   *   Um objeto de persistência
   * @param bool $remember
   *   O indicativo de que deve memorizar este código na sessão
   *                                           
   * @return bool
   */
  public function persist(
    $persistable,
    bool $remember = false
  ): bool;
  
  /**
   * Remove a persistência para a sessão atual.
   * 
   * @return bool
   */
  public function forget(): bool;
  
  /**
   * Remove o código de persistência fornecido.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return bool
   */
  public function remove($code): bool;
  
  /**
   * Libera persistências para o usuário especificado, independente da
   * sessão em que ela se encontra.
   * 
   * @param PersistableInterface $persistable
   *   O objeto de persistência
   * @param bool|boolean $forget
   *   O indicativo se deve encerrar a sessão atual
   */
  public function flush(
    $persistable,
    bool $forget = true
  ): void;
}