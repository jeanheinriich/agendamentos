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
 * Essa é uma trait (característica) simples de inclusão de mensagens
 * flash para envio ao usuário que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Flash;

use Core\Helpers\InterpolateTrait;

trait FlashTrait
{
  /**
   * Os métodos para interpolar valores nas mensagens
   */
  use InterpolateTrait;
  
  /**
   * Adiciona uma mensagem flash na próxima requisição
   * 
   * @param string $name
   *   O nome (título) da mensagem
   * @param string $message
   *   A mensagem (conteúdo) a ser exibida
   * @param array $params
   *   Os parâmetros a serem substituídos na mensagem
   */
  protected function flash(string $name, string $message,
    array $params = [])
  {
    // Interpola os valores dos parâmetros na mensagem
    $message = $this->interpolate($message, $params);

    $this->container['flash']->addMessage($name, $message);
  }
  
  /**
   * Adiciona uma mensagem flash na requisição atual
   * 
   * @param string $name
   *        O nome (título) da mensagem
   * @param string $message
   *   A mensagem (conteúdo) a ser exibida
   * @param array $params
   *   Os parâmetros a serem substituídos na mensagem
   */
  protected function flashNow(string $name, string $message,
    array $params = [])
  {
    // Interpola os valores dos parâmetros na mensagem
    $message = $this->interpolate($message, $params);
    
    $this->container['flash']->addMessageNow($name, $message);
  }
}
