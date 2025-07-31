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
 * A interface para um manipulador de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */


namespace Core\Authorization\Users;

use Core\Authorization\Contractors\Contractor;
use Core\Authorization\Users\UserInterface;

interface UsersManagerInterface
{
  /**
   * Localiza um usuário pela ID fornecida.
   * 
   * @param int $userID
   *   O ID do usuário
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou false se não encontrar
   */
  public function findById(int $userID);
  
  /**
   * Localiza um usuário pelas credenciais fornecidas.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * @param Contractor $contractor
   *   O objeto com os dados do contratante
   * @param bool $hasAdministrator
   *   Se o usuário é administrador
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou nulo se não encontrar
   */
  public function findByCredentials(
    array $credentials,
    Contractor $contractor,
    bool $hasAdministrator
  ): ?UserInterface;
  
  /**
   * Encontra um usuário pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou falso se não localizar
   */
  public function findByPersistenceCode($code);
  
  /**
   * Encontra um usuário pelo código de reativação da conta.
   * 
   * @param mixed $code
   *   O código de reativação da conta
   * 
   * @return UserInterface|false
   *   Os dados do usuário ou falso se não localizar
   */
  public function findByReactivationCode($code);
  
  /**
   * Encontra um usuário pelo código de lembrança.
   * 
   * @param mixed $code
   *   O código de lembrança
   * 
   * @return UserInterface
   *   Os dados do usuário ou nulo se não localizar
   *
   * @throws ReminderException
   *   Em caso do código de lembrança não for localizado ou tenha
   *   expirado
   */
  public function findByReminderCode($code);

  /**
   * Registra o login realizado com sucesso para o usuário especificado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguiu registrar
   */
  public function registerSuccessfulLogin(UserInterface $user);

  /**
   * Registra o logout realizado com sucesso para o usuário
   * especificado.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguiu registrar
   */
  public function registerSuccessfulLogout(UserInterface $user);

  /**
   * Valida a senha do usuário especificado.
   * 
   * @param UserInterface $user
   *   O objeto com os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validatePassword(
    UserInterface $user,
    array $credentials
  ): bool;
  
  /**
   * Verifica se o usuário especificado é válido para criação.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validForCreation(array $credentials): bool;
  
  /**
   * Verifica se o usuário especificado é válido para modificação.
   * 
   * @param mixed $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return bool
   */
  public function validForUpdate($user, array $credentials): bool;
  
  /**
   * Cria um usuário.
   * 
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return UserInterface
   *   Os dados do usuário
   */
  public function create(array $credentials): UserInterface;
  
  /**
   * Atualiza os dados de um usuário.
   * 
   * @param mixed $user
   *   Os dados do usuário
   * @param array $credentials
   *   As credenciais do usuário
   * 
   * @return UserInterface
   *   Os dados do usuário modificados
   */
  public function update($user, array $credentials): UserInterface;
  
  /**
   * Grava as informações de um logon bem-sucedido.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return UserInterface|false
   *   Retorna os dados do usuário ou false se não conseguir gravar
   */
  public function recordLogin(UserInterface $user);
}
