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
 * Um serviço de mensagens flash modificado para permitir lidar
 * corretamente com um middleware de sessões.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Flash;

use RuntimeException;
use Slim\Flash\Messages as FlashMessages;

class Messages
  extends FlashMessages
{
  /**
   * Muda o armazenamento interno para o armazenamento em sessão.
   * 
   * @throws RuntimeException
   *   Se a sessão não puder ser encontrada
   */
  public function changeToSessionStorage(): void
  {
    if (!isset($_SESSION)) {
      throw new RuntimeException("Falha no middleware de mensagens "
        . "flash. Sessão não encontrada."
      );
    }

    $this->storage = &$_SESSION;

    // Carregar mensagens da solicitação anterior
    if ( isset($this->storage[$this->storageKey])
         && is_array($this->storage[$this->storageKey]) ) {
      $this->fromPrevious = $this->storage[$this->storageKey];
    }
    
    $this->storage[$this->storageKey] = [];
  }
}
