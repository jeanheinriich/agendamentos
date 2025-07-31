<?php
/*
 * This file is part of the payment's API library.
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
 * Uma característica (trait) que cria os métodos mágicos do PHP que
 * outras classes podem importar.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab;

trait MagicTrait
{
  /**
   * Atributos que não existem nesta classe.
   *
   * @var array
   */
  protected $trash = [];

  /**
   * Define o valor de um atributo.
   *
   * @param string $name
   *   O nome do campo
   * @param mixed $value
   *   O valor do campo
   */
  public function __set(string $name, $value): void
  {
    if (property_exists($this, $name)) {
      $this->$name = $value;
    } else {
      $this->trash[$name] = $value;
    }
  }

  /**
   * Obtém um valor de um atributo.
   *
   * @param string $name
   *   O nome do campo
   *
   * @return mixed|null
   */
  public function __get(string $name)
  {
    if (property_exists($this, $name)) {
      $method = 'get' . ucwords($name);

      return $this->{$method}();
    } elseif (isset($this->trash[$name])) {
      return $this->trash[$name];
    }

    return null;
  }

  /**
   * Determine se um atributo existe.
   *
   * @param string $key
   *   O nome do atributo
   * 
   * @return bool
   */
  public function __isset($key): bool
  {
    return isset($this->$key) || isset($this->trash[$key]);
  }

  /**
   * Converte os atributos para uma matriz.
   * 
   * @return array
   */
  public function toArray(): array
  {
    $vars = array_keys(get_class_vars(self::class));
    $aRet = [];
    foreach ($vars as $var) {
      $methodName = 'get' . ucfirst($var);
      $aRet[$var] = method_exists($this, $methodName)
        ? $this->$methodName()
        : $this->$var
      ;

      if (is_object($aRet[$var]) && method_exists($aRet[$var], 'toArray')) {
        $aRet[$var] = $aRet[$var]->toArray();
      }
    }

    return $aRet;
  }
}
