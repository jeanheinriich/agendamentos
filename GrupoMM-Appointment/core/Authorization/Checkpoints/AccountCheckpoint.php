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
 * Um sistema de verificação de uma conta de um usuário, de forma a
 * bloquear contas inativas, suspensas e/ou expiradas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Checkpoints;

use Carbon\Carbon;
use Core\Authorization\Users\UserInterface;
use Core\Exceptions\AccountRestrictionException;

class AccountCheckpoint
  implements CheckpointInterface
{
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor de nossa classe.
   */
  public function __construct() { }


  // -----------------------[ Implementações da CheckpointInterface ]---

  /**
   * Ponto de verificação após o login de um usuário. Retorna false para
   * negar.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function login(UserInterface $user): bool
  {
    return $this->validateAccount($user);
  }

  /**
   * Ponto de verificação para analisar quando um usuário já está
   * armazenado na sessão (autenticado).
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function check(UserInterface $user): bool
  {
    return $this->validateAccount($user);
  }

  /**
   * Ponto de verificação para quando uma tentativa de logon com falha é
   * registrada. O resultado do método não afeta nada, pois o login
   * falhou.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  public function fail(
    ?UserInterface $user = null
  ): void
  {
    // Não precisa realizar quaisquer verificações
  }

  /**
   * Ponto de verificação para quando um usuário não registrado tenta
   * realizar a autenticação. O resultado do método não afeta nada, pois
   * o login já falhou.
   * 
   * @param string $username
   *   O nome do usuário
   */
  public function unknown(string $username): void
  {
    // Não precisa realizar quaisquer verificações
  }


  // ---------------------------------------[ Outras implementações ]---

  /**
   * Verifica quaisquer não conformidades numa conta de usuário.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  protected function validateAccount(UserInterface $user): bool
  {
    // Verifica se a conta do usuário está bloqueada
    if ($user->blocked) {
      $this->throwException("Sua conta encontra-se bloqueada.", $user,
        'blocked'
      );

      return false;
    }

    // Verifica se a entidade na qual o usuário está vinculado está
    // bloqueada
    $entity = $user->entity;
    if ($entity->blocked) {
      $type = $entity->getEntityType();
      $name = $entity->name;
      $this->throwException("A conta do {$type} {$name} encontra-se "
        . "bloqueada. Entre em contato com o serviço de atendimento.",
        $user, 'blocked'
      );

      return false;
    }

    // Verifica se a conta do usuário está expirada
    if ($user->expires) {
      // Determina se está vencido
      $today = Carbon::today();
      $expiresat = Carbon::parse($user->expiresat);

      if ($today->greaterThan($expiresat)) {
        $expirationDate = $expiresat->format('d/m/Y');
        $this->throwException("Sua conta encontra-se expirada desde "
          . "{$expirationDate}.", $user, 'expired'
        );

        return false;
      }
    }

    // Verifica se a conta do usuário está suspensa
    if ($user->suspended) {
      $this->throwException("Sua conta encontra-se suspensa. Verifique "
        . "seu e-mail com as instruções para reativá-la.", $user,
        'suspended'
      );

      return false;
    }

    return true;
  }

  /**
   * Lança uma exceção de limitação de autenticação.
   *
   * @param string $message
   *   A mensagem a ser relatada
   * @param UserInterface $user
   *   O usuário relacionado com a mensagem
   * 
   * @throws AccountRestrictionException
   */
  protected function throwException(
    string $message,
    UserInterface $user,
    string $type
  ): void
  {
    $exception = new AccountRestrictionException($message);

    $exception->setUser($user);
    $exception->setType($type);

    throw $exception;
  }
}
