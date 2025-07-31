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
 * Uma classe de erro para limitar os erros de autenticação, provocando
 * um atraso entre autenticações.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Carbon\Carbon;
use RuntimeException;

class LoginDelayException
  extends RuntimeException
{
  /**
   * O tempo de atraso entre autenticações (em segundos).
   * 
   * @var integer
   */
  protected $delay;
  
  /**
   * O tipo de limitação que causou a exceção.
   * 
   * @var string
   */
  protected $type;
  
  /**
   * O nome do usuário ou endereço IP da ocorrência.
   * 
   * @var string
   */
  protected $parameter;
  
  /**
   * Obtém o tempo de atraso entre autenticações.
   * 
   * @return int
   *   O tempo de atraso entre autenticações
   */
  public function getDelay(): int
  {
    return $this->delay;
  }
  
  /**
   * Define o tempo de atraso entre autenticações.
   * 
   * @param integer $delay
   *   O tempo de atraso
   */
  public function setDelay(int $delay): void
  {
    $this->delay = $delay;
  }
  
  /**
   * Retorna o tipo de limitação que causou a exceção.
   * 
   * @return string
   *   O tipo de limitação
   */
  public function getType(): string
  {
    return $this->type;
  }

  /**
   * Define o tipo de limitação que causou a exceção.
   * 
   * @param string $type
   *   O novo tipo de limitação
   */
  public function setType(string $type): void
  {
    $this->type = $type;
  }
  
  /**
   * Retorna o endereço IP ou nome do usuário limitado.
   * 
   * @return string
   *   O nome do usuário ou endereço IP
   */
  public function getParameter(): string
  {
    return $this->parameter;
  }

  /**
   * Define o endereço IP ou nome do usuário limitado.
   * 
   * @param string $parameter
   *   O endereço IP ou nome do usuário
   */
  public function setParameter(string $parameter): void
  {
    $this->parameter = $parameter;
  }
  
  /**
   * Retorna o tempo em que a limitação será mantida.
   * 
   * @return Carbon
   */
  protected function getFree()
  {
    return Carbon::now()->addSeconds($this->delay);
  }

  /**
   * Retorna um registro do que estamos bloqueando.
   *
   * @return string
   *   O nome da limitação
   */
  public function getLog(): string
  {
    $time = $this->getFree();
    $parameter = $this->parameter;

    switch ($this->type) {
      case 'ip':
        // Restrição para um IP
        $value = "Bloqueada a autenticação por {$time} segundo(s) para "
          . "o IP {$parameter}";

        break;
      case 'user':
        // Restrição para um usuário
        $value = "Bloqueada a autenticação por {$time} segundo(s) para "
          . "a conta do usuário {$parameter}";
        
        break;
      default:
        // Restrição global
        $value = "Bloqueada a autenticação globalmente por {$time} "
          . "segundo(s) para todos as contas de usuários (independente "
          . "do endereço IP)";

        break;
    }

    return $value;
  }
}
