<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Um serviço para manipular valores armazenados na sessão deste
 * aplicativo.
 * 
 * O suporte a sessão permite que se armazene dados entre solicitações
 * deste aplicativo. A cada visitante/usuário que acessa o website é
 * atribuída uma identificação única, chamada identificação de sessão.
 * Quando este visitante acessa o site, será verificado na sua requisição
 * se esta possui um cookie com o id específico da sessão. Se este for o
 * caso, o ambiente previamente salvo é recriado, senão um novo ambiente
 * é instanciado. Este trabalho é realizado pelo middleware de sessão.
 *
 * Este manipulador permite acessar estes dados armazenados de maneira
 * simplificada no aplicativo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Sessions;

use Countable;
use IteratorAggregate;
use Traversable;

class SessionManager
  implements SessionInterface, Countable, IteratorAggregate
{
  /**
   * O construtor de nosso manipulador de sessão.
   */
  public function __construct() { }
  
  /**
   * O destrutor de nosso manipulador de sessão, responsável por gravar
   * os valores armazenados na sessão.
   */
  public function __destruct()
  {
    $this->writeSession();
  }
  
  // ===========================================[ Métodos mágicos ]=====

  /**
   * É utilizado para ler dados da sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * 
   * @return mixed
   *   O valor armazenado
   */
  public function __get(string $name)
  {
    return $this->get($name);
  }

  /**
   * É executado ao escrever dados na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * @param mixed $value
   *   O valor a ser armazenado
   *
   * @return void
   */
  public function __set(string $name, $value): void
  {
    $this->set($name, $value);
  }

  /**
   * É acionado por uma chamada à isset() ou empty() em um dado
   * armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * 
   * @return boolean
   *   O indicativo se esta chave aponta para um valor armazenado
   */
  public function __isset(string $name): bool
  {
    return $this->has($name);
  }

  /**
   * É invocada quando uma chamada à unset() é utilizada em um dado
   * armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   *
   * @return void
   */
  public function __unset(string $name): void
  {
    $this->delete($name);
  }
  
  // =========================[ Implementação da SessionInterface ]=====

  /**
   * Recupera um valor armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * @param mixed $default
   *   O valor padrão a ser devolvido caso a chave não possua um valor
   * atribuído
   * 
   * @return mixed
   *   O valor armazenado
   */
  public function get(string $name, $default = null): mixed
  {
    return $this->has($name)
      ? $_SESSION[$name]
      : $default
    ;
  }
  
  /**
   * Armazena um valor na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * @param mixed $value
   *   O valor a ser armazenado
   *
   * @return $this
   *   A instância da entidade
   */
  public function set(string $name, $value)
  {
    $_SESSION[$name] = $value;
    
    return $this;
  }

  /**
   * Verifica se um valor foi armazenado na sessão.
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   * 
   * @return boolean
   *   O indicativo de que a chave corresponde à um valor armazenado
   */
  public function has(string $name): bool
  {
    return isset($_SESSION[$name]);
  }
  
  /**
   * Remove um valor da sessão
   * 
   * @param string $name
   *   A chave que identifica o valor armazenado
   *
   * @return $this
   *   A instância da entidade
   */
  public function delete(string $name)
  {
    if ($this->has($name)) {
      unset($_SESSION[$name]);
    }
    
    return $this;
  }

  /**
   * Remove todos os valores armazenados na sessão.
   *
   * @return $this
   *   A instância da entidade
   */
  public function clear()
  {
    $_SESSION = [];
    
    return $this;
  }

  
  // ===================================[ Manipuladores da sessão ]=====
  
  /**
   * Verifica se a sessão está ativa.
   *
   * @return bool
   */
  public function isActive(): bool
  {
    return session_status() === PHP_SESSION_ACTIVE;
  }

  /**
   * Escreve todos os valores armazenados no arquivo de sessão.
   * 
   * @return void
   */
  protected function writeSession(): void
  {
    session_write_close();
  }

  /**
   * Libera todos os valores da sessão e limpa o cookie que armazena os
   * a sessão no cliente.
   * 
   * @return void
   */
  public function forgetSession(): void
  {
    if ($this->isActive()) {
      // Limpa todos os valores armazenados
      $_SESSION = [];

      // Recupera as informações do cookie
      $cookie = session_get_cookie_params();

      // Limpa o cookie
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie['path'],
        $cookie['domain'],
        $cookie['secure'],
        $cookie['httponly']
      );

      // Destrói a sessão
      session_destroy();
    }
  }
  
  /**
   * Recupera ou regenera a ID da sessão corrente
   * 
   * @param boolean $new
   *   A flag indicativa de forçar a regeneração do ID da sessão
   * 
   * @return string
   *   A identificação única desta sessão
   */
  public static function id($new = false): string
  {
    if ($new && session_id()) {
      session_regenerate_id(true);
    }
    
    return session_id() ?: '';
  }


  // ================================[ Implementação da Countable ]=====

  /**
   * Conta os elementos armazenados na sessão.
   * 
   * @return int
   *   A quantidade de elementos armazenados
   */
  public function count(): int
  {
    return count($_SESSION);
  }


  // ========================[ Implementação da IteratorAggregate ]=====
  
  /**
   * Permite iterar sobre os valores armazenados na sessão.
   * 
   * @return Traversable
   */
  public function getIterator(): Traversable
  {
    return (function () {
      foreach ($_SESSION as $key => $value) {
        yield $key => $value;
      }
    })();
  }
}
