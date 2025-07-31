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
 * Um sistema gerenciador da ativação de usuários. Faz uso de códigos de
 * ativação e permite verificar se o usuário foi devidamente ativado.
 *
 * Não confundir o sistema de ativação com o sistema de bloqueio. Um 
 * usuário ativo pode ser bloqueado a qualquer momento. A ativação
 * permite o cadastro de usuários pela internet, mediante o envio de
 * um e-mail para o usuário, garantindo a validade do e-mail.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */


namespace Core\Authorization\Activations;

use Carbon\Carbon;
use Core\Authorization\Users\UserInterface;

class ActivationsManager
  implements ActivationsManagerInterface
{
  /**
   * O tempo de expiração da ativação, em segundos.
   *
   * @var integer
   */
  protected $expires = 259200;
  
  // ---------------[ Implementações da ActivationsManagerInterface ]---
  
  /**
   * Cria um novo registro e código de ativação.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return Activation
   *   Os dados da ativação
   */
  public function create(UserInterface $user): Activation
  {
    // Gera um novo código de ativação
    $code = $this->generateActivationCode();
    
    // Monta uma nova ativação e adiciona
    $newActivation = new Activation();
    $newActivation->fill(compact('code'));
    $newActivation->userid = $user->getUserId();
    $newActivation->save();
    
    return $newActivation;
  }
  
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
  ): ?Activation
  {
    $expires = $this->expires();
    
    if ($code) {
      $activation = Activation::where("userid", $user->getUserId())
        ->where("completed", "false")
        ->where("createdat", '>', $expires)
        ->where("code", $code)
        ->first()
      ;
    } else {
      $activation = Activation::where("userid", $user->getUserId())
        ->where("completed", "false")
        ->where("createdat", '>', $expires)
        ->first()
      ;
    }
    
    return $activation;
  }
  
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
  public function complete(UserInterface $user, string $code): bool
  {
    $expires = $this->expires();
    
    $activation = Activation::where("userid", $user->getUserId())
      ->where("code", $code)
      ->where("completed", "false")
      ->where("createdat", '>', $expires)
      ->first()
    ;
    
    if ($activation === null) {
      return false;
    }
    
    // Indica que o usuário completou a ativação de seu usuário
    $activation->completed = true;
    $activation->completedat = Carbon::now();
    $activation->save();
    
    return true;
  }
  
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
  public function completed(UserInterface $user)
  {
    $activation = Activation::where("userid", $user->getUserId())
      ->where("completed", "true")
      ->first()
    ;
    
    return $activation ?: false;
  }
  
  /**
   * Remove uma ativação existente (desativa o usuário).
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * 
   * @return bool
   */
  public function remove(UserInterface $user): bool
  {
    $activation = $this->completed($user);
    
    if ($activation === false) {
      return false;
    }
    
    return $activation->delete();
  }
  
  /**
   * Remove os códigos de ativação expirados.
   * 
   * @return bool
   */
  public function removeExpired(): bool
  {
    $expires = $this->expires();
    
    return Activation::where("completed", "false")
      ->where("created_at", '<', $expires)
      ->delete()
    ;
  }


  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Determina o tempo de expiração.
   * 
   * @return Carbon
   *   A data/hora de expiração
   */
  protected function expires(): Carbon
  {
    return Carbon::now()->subSeconds($this->expires);
  }
  
  /**
   * Gera um código de ativação.
   * 
   * @return string
   *   O código de ativação
   */
  protected function generateActivationCode(): string
  {
    $length = 32;
    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
  }
}
