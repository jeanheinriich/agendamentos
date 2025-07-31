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
 * Um sistema de verificação da ativação de uma conta de um usuário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Checkpoints;

use Core\Authorization\Activations\ActivationsManagerInterface;
use Core\Authorization\Users\UserInterface;
use Core\Exceptions\NotActivatedException;

class ActivationCheckpoint
  implements CheckpointInterface
{
  /**
   * O gerenciador de ativações
   *
   * @var ActivationsManagerInterface
   */
  protected $activations;

  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor de nossa classe.
   * 
   * @param ActivationsManagerInterface $activations
   *   O gerenciador de ativações
   */
  public function __construct(ActivationsManagerInterface $activations)
  {
    $this->activations = $activations;
  }


  // -----------------------[ Implementações da CheckpointInterface ]---
  
  /**
   * Ponto de verificação após o login de um usuário. Retorna false para
   * negar.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   * 
   * @throws NotActivatedException
   *   Em caso do usuário não estar ativado
   */
  public function login(UserInterface $user): bool
  {
    return $this->checkActivation($user);
  }

  /**
   * Ponto de verificação para analisar quando um usuário está
   * atualmente armazenado na sessão.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   * 
   * @throws NotActivatedException
   *   Em caso do usuário não estar ativado
   */
  public function check(UserInterface $user): bool
  {
    return $this->checkActivation($user);
  }

  /**
   * Ponto de verificação para quando uma tentativa de logon com falha é
   * registrada. O usuário nem sempre é passado e o resultado do método
   * não afeta nada, pois o login falhou.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  public function fail(UserInterface $user = null): void
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
   * Verifica o estado da ativação de uma conta de usuário.
   *
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   * 
   * @throws NotActivatedException
   *   Em caso do usuário não estar ativado
   */
  protected function checkActivation(UserInterface $user)
  {
    // Verifica se a conta do usuário está ativada
    $completed = $this->activations->completed($user);

    if (!$completed) {
      $this->throwException("Sua conta ainda não foi ativada. Siga as "
        . "instruções contidas no e-mail enviado para o endereço "
        . "que você informou no cadastro de sua conta. Se você não "
        . "recebeu o e-mail, por gentileza, contacte o administrador "
        . "do sistema.", $user);

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
   * @throws NotActivatedException
   */
  protected function throwException($message, UserInterface $user): void
  {
    $exception = new NotActivatedException($message);

    $exception->setUser($user);

    throw $exception;
  }
}
