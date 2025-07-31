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
 * Um manipulador de tokens de lembrança para redefinição da senha dos
 * usuários de forma segura.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Reminders;

use Core\Authorization\Reminders\Reminder;
use Core\Authorization\Users\UserInterface;
use Core\Authorization\Users\UsersManagerInterface;
use Core\Exceptions\ReminderException;
use Carbon\Carbon;

class RemindersManager
  implements RemindersManagerInterface
{
  /**
   * A instância do gerenciador de usuários.
   *
   * @var UsersManagerInterface
   */
  protected $users;
  
  /**
   * O tempo de expiração em segundos (padrão 4h) do token.
   *
   * @var integer
   */
  protected $expires = 14400;
  
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do manipulador de tokens de lembrança para redefinição
   * da senha dos usuários.
   * 
   * @param UsersManagerInterface $users
   *   O gerenciados de usuários
   * @param boolean $expires
   *   O tempo de expiração de cada token (nulo se não usar)
   */
  public function __construct(
    UsersManagerInterface $users,
    $expires = null
  )
  {
    // Armazena o gerenciador de usuários
    $this->users = $users;
    
    // Determina o tempo de expiração
    if (isset($expires)) {
      $this->expires = $expires;
    }
  }

  
  // -----------------[ Implementações da RemindersManagerInterface ]---
  
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
  public function create(UserInterface $user): ReminderInterface
  {
    // Primeiramente remove quaisquer códigos expirados
    $this->removeExpired();

    // Verifica se temos algum código de recuperação de senha válido,
    // independente do token
    $reminder = $this->exists($user);

    if (!$reminder) {
      // Criamos um novo lembrete    
      $reminder = new Reminder();

      // Gera um token para associar os dados do usuário de maneira segura
      $token  = $reminder->generateToken();

      // Grava um novo registro de lembrança
      $reminder->fill([
        'token'     => $token,
        'userid'    => $user->getUserId(),
        'expires'   => $this->timeToExpire(),
        'completed' => false
      ]);
      $reminder->save();
    }
    
    return $reminder;
  }
  
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
  )
  {
    if ($token) {
      $reminder = Reminder::where("userid", $user->getUserId())
        ->where("completed", "false")
        ->where("createdat", '>', $this->expiredTime())
        ->where("token", $token)
        ->first()
      ;
    } else {
      $reminder = Reminder::where("userid", $user->getUserId())
        ->where("completed", "false")
        ->where("createdat", '>', $this->expiredTime())
        ->first()
      ;
    }
    
    return $reminder ?: false;
  }
  
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
    string $token,
    string $password
  ): bool
  {
    $reminder =  Reminder::where("userid", $user->getUserId())
      ->where("token", $token)
      ->where("completed", "false")
      ->first()
    ;

    if ($reminder === null) {
      // Agora disparamos uma exceção indicando que o código de lembrança
      // não foi localizado ou já expirou
      $exception = new ReminderException("O token de redefinição de "
        . "senha é inválido ou já expirou. Por gentileza, solicite um "
        . "novo token e reinicie o procedimento.");
      $exception->setToken($token);
      
      throw $exception;

      return false;
    }
    
    $credentials = [
      'username' => $user->username,
      'password' => $password
    ];
    
    $valid = $this->users->validForUpdate($user, $credentials);
    
    if ($valid === false) {
      return false;
    }
    
    $this->users->update($user, $credentials);
    
    $reminder->fill([
      'completed'    => true,
      'completedat' => Carbon::now(),
    ]);
    
    $reminder->save();
    
    return true;
  }
  
  /**
   * Remove os códigos de lembrete expirados.
   * 
   * @return bool
   */
  public function removeExpired(): bool
  {
    // Determina o tempo de expiração em segundos
    $expires = $this->expiredTime();
    
    return Reminder::where("completed", "false")
      ->where("createdat", '<', $expires)
      ->delete()
    ;
  }


  // ---------------------------------------[ Outras implementações ]---

  /**
   * Retorna o tempo para expirar um novo token.
   * 
   * @return Carbon
   *   O tempo para expirar
   */
  protected function timeToExpire(): Carbon
  {
    return Carbon::now()->addSeconds($this->expires);
  }
  
  /**
   * Retorna o tempo a partir do qual um token é considerado expirado.
   * 
   * @return Carbon
   *   O valor (em segundos) a partir do qual um token é considerado
   * expirado
   */
  protected function expiredTime(): Carbon
  {
    return Carbon::now()->subSeconds($this->expires);
  }
}
