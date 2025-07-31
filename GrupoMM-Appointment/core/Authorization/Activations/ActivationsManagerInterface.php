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
 * A interface para um manipulador de ativação de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */


namespace Core\Authorization\Activations;

use Core\Authorization\Users\UserInterface;

interface ActivationsManagerInterface
{
  /**
   * Cria um novo registro e código de ativação.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return Activation
   *   Os dados da ativação
   */
  public function create(UserInterface $user): Activation;
  
  /**
   * Verifica se existe uma ativação válida para o usuário especificado.
   * Se for fornecido um código de ativação, então tenta localizar
   * usando este código. Retorna um objeto contendo os dados da ativação
   * ou nulo se não encontrar.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string|null $code
   *   O código de ativação (opcional)
   * 
   * @return Activation|null
   *   Os dados de ativação ou nulo se não localizar
   */
  public function exists(
    UserInterface $user,
    ?string $code = null
  ): ?Activation;
  
  /**
   * Completa a ativação para o usuário especificado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string $code
   *   O código de ativação
   * 
   * @return bool
   */
  public function complete(UserInterface $user, string $code): bool;
  
  /**
   * Verifica se o usuário foi devidamente ativado no sistema.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return Activation
   *   Retorna os dados da ativação ou falso se o usuário ainda não foi
   * ativado
   */
  public function completed(UserInterface $user);
  
  /**
   * Remove uma ativação existente (desativa o usuário).
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function remove(UserInterface $user): bool;
  
  /**
   * Remove os códigos de ativação expirados.
   * 
   * @return bool
   */
  public function removeExpired(): bool;
}
