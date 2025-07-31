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
 * Classe responsável pela comparação de uma senha com seu hash.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Core\Hashing\Sha384Hasher;
use Respect\Validation\Rules\AbstractRule;

class MatchesPassword
  extends AbstractRule
{
  /**
   * A instância do gerador do número de verificação (Hash).
   *
   * @var Sha384Hasher
   */
  protected $hasher;

  /**
   * A senha atual do usuário.
   *
   * @var string
   */
  protected $currentPassword;

  /**
   * Inicializa a regra de validação.
   */
  public function __construct(string $currentPassword)
  {
    // Cria um manipulador de hashing para proteção da senha do usuário
    $this->hasher = new Sha384Hasher();

    // Armazena a senha atual do usuário
    $this->currentPassword = $currentPassword;
  }

  /**
   * Valida o valor de um campo de senha.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($password)
  {
    return $this->hasher->checkHashFromValue($password,
      $this->currentPassword
    );
  }
}
