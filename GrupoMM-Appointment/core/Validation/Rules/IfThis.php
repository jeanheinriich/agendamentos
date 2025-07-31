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
 * Classe responsável por verificar se uma condição é satisfeita. Em
 * função do resultado, determinada validação é executada.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Rules;

use Respect\Validation\Exceptions\AlwaysInvalidException;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Rules\AlwaysInvalid;
use Respect\Validation\Validatable;

class IfThis
  extends AbstractRule
{
  /**
   * A condição de validação
   *
   * @var bool
   */
  public $condition;

  /**
   * Uma regra a ser validada se a condição for verdadeira.
   *
   * @var Validatable
   */
  public $then;

  /**
   * Uma regra a ser validada se a condição for falsa.
   *
   * @var Validatable
   */
  public $else;

  /**
   * Inicializa a regra de validação.
   */
  public function __construct(bool $condition, Validatable $then,
    ?Validatable $else = null)
  {
    // Armazena as regras a serem validadas conforme a condição
    $this->condition = $condition;
    $this->then      = $then;
    if (null === $else) {
      $else = new AlwaysInvalid();
      $else->setTemplate(AlwaysInvalidException::SIMPLE);
    }
    $this->else = $else;
  }

  /**
   * Valida o valor conforme uma condição.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function validate($input)
  {
    if ($this->condition === true) {
      return $this->then->validate($input);
    }

    return $this->else->validate($input);
  }

  /**
   * Permite afirmar se o valor é válido.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function assert($input): bool
  {
    if ($this->condition === true) {
      return $this->then->assert($input);
    }

    return $this->else->assert($input);
  }

  /**
   * Permite verificar se o valor é válido.
   *
   * @param mixed $input
   *   O valor a ser validado
   *
   * @return bool
   */
  public function check($input)
  {
    if ($this->condition === true) {
      return $this->then->check($input);
    }

    return $this->else->check($input);
  }

  /**
   * Atribui o nome do campo nas condições.
   *
   * @param string $name
   *   O nome do campo (rótulo)
   */
  public function setName($name)
  {
    $this->then->setName($name);
    $this->else->setName($name);

    return parent::setName($name);
  }
}
