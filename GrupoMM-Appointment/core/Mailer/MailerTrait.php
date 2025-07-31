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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipulação e envio de e-mails que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer;

use Core\Exceptions\TemplateNotFoundException;

trait MailerTrait
{
  /**
   * Envia uma mensagem de e-mail.
   * 
   * @param string $templateName
   *   O nome da template com o conteúdo do e-mail
   * @param array $data
   *   Os dados usados para renderizarmos o template e para setar o
   *   envio do e-mail
   * @param callable|null $callback
   *   Uma função a ser executada antes de renderizar o template
   * 
   * @return bool
   *   O resultado da operação
   * 
   * @throws TemplateNotFoundException
   *   No caso do template não ser localizado
   */
  public function sendEmail(
    string $templateName,
    array $data,
    ?callable $callback = null
  ): bool
  {
    // Determinamos o template
    $templatePath = $this->container['settings']['renderer']['templatePath'];
    $template = 'mail/' . $templateName  . '.twig';
    
    // Verifica se o template existe
    if (file_exists($templatePath . '/' . $template)) {
      // Faz a renderização do e-mail e envia
      $mailer = $this->container->get('mailer');
      
      return $mailer->send($template, $data, $callback);
    } else {
      throw new TemplateNotFoundException($template);
    }
  }
}
