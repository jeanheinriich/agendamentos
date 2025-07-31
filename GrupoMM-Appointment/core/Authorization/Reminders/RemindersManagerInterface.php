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
 * A interface para um manipulador de tokens de lembrança para
 * redefinição da senha dos usuários de forma segura.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Reminders;

use Core\Authorization\Users\UserInterface;

interface RemindersManagerInterface
{
  /**
   * Cria um novo registro e token de lembrança para redefinição da
   * senha do usuário.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return ReminderInterface
   *   Os dados do token
   */
  public function create(UserInterface $user): ReminderInterface;
  
  /**
   * Verifica se existe um lembrete válido para o usuário indicado e/ou
   * para o token informado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string|null $token
   *   O string com o valor do token
   * 
   * @return ReminderInterface|false
   *   Os dados de lembrança ou falso se não encontrar
   */
  public function exists(
    UserInterface $user,
    ?string $token = null
  );
  
  /**
   * Completa o lembrete para o usuário especificado, alterando sua
   * senha e indicando que o lembrete foi concluído com sucesso.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string $code
   *   O código do token que usamos para redefinir a senha do usuário
   * @param string $password
   *   A nova senha do usuário
   * 
   * @return bool
   */
  public function complete(
    UserInterface $user,
    string $code,
    string $password
  ): bool;
  
  /**
   * Remove os códigos de lembrete expirados.
   * 
   * @return bool
   */
  public function removeExpired(): bool;
}
