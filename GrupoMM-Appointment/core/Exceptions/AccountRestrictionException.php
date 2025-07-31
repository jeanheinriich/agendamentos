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
 * Uma classe para notificar erros de restrições na conta de um usuário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Core\Authorization\Users\UserInterface;
use RuntimeException;

class AccountRestrictionException
  extends RuntimeException
{
  /**
   * O usuário que causou a exceção.
   *
   * @var UserInterface
   *   Os dados do usuário
   */
  protected $user;

  /**
   * O tipo de restrição que causou a exceção.
   *
   * @var string
   *   O nome da restrição
   */
  protected $type;

  /**
   * Retorna o usuário que causou a exceção.
   *
   * @return UserInterface
   *   Os dados do usuário
   */
  public function getUser()
  {
    return $this->user;
  }

  /**
   * Define o usuário associado à autenticação (não realiza a
   * autenticação do mesmo).
   *
   * @var UserInterface
   *   Os dados do usuário
   */
  public function setUser(UserInterface $user): void
  {
    $this->user = $user;
  }

  /**
   * Retorna o tipo de restrição que causou a exceção.
   *
   * @return string
   *   O tipo da restrição
   */
  public function getType(): string
  {
    return $this->type;
  }

  /**
   * Define o tipo de restrição que causou a exceção.
   *
   * @var string
   *   O tipo da restrição
   */
  public function setType(string $type): void
  {
    $this->type = $type;
  }

  /**
   * Retorna o nome da restrição que causou a exceção.
   *
   * @return string
   *   O nome da restrição
   */
  public function getTypeName(): string
  {
    switch ($this->type) {
      case 'blocked':
        $value = 'bloqueada';

        break;
      case 'expired':
        $value = 'expirada';
        
        break;
      case 'suspended':
        $value = 'suspensa';
        
        break;
      default:
        $value = 'com uma restrição desconhecida';

        break;
    }

    return $value;
  }
}
