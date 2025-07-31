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
 * Classe responsável por extender o Twig permitindo a renderização de
 * um e-mail usando um template Twig, obtendo o corpo do e-mail e o
 * assunto diretamente do template. Permite também que imagens sejam
 * embutidas corretamente no corpo do e-mail.
 */

namespace Core\Mailer\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Swift_Message;
use Swift_Image;

class MailTemplateExtension
  extends AbstractExtension
{
  /**
   * A nossa mensagem de e-mail.
   * 
   * @var Swift_Message
   */
  private $message;

  /**
   * O caminho para a pasta onde estão armazenadas as imagens embutidas.
   * 
   * @var String
   */
  private $pathForEmbedImages;

  /**
   * Seta o conteúdo da mensagem sendo renderizada.
   * 
   * @param Swift_Message $message
   *   A mensagem de e-mail para a qual estamos renderizando o conteúdo
   */
  public function setSwiftMessage(Swift_Message $message): void
  {
    $this->message = $message;
  }

  /**
   * Recupera o conteúdo da mensagem renderizada, permitindo obter os
   * dados de imagens embutidas no processo de renderização.
   * 
   * @return Swift_Message
   *   A mensagem de e-mail para a qual estamos renderizando o conteúdo
   */
  public function getSwiftMessage(): Swift_Message
  {
    return $this->message;
  }

  /**
   * Seta o caminho onde estão armazenadas as imagens embutidas.
   * 
   * @param string $path
   *   O caminho onde estão armazenadas as imagens embutidas
   */
  public function setPathForEmbedImages(string $path): void
  {
    $this->pathForEmbedImages = $path;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('embedImage', [$this, 'embedImage'])
    ];
  }
  
  /**
   * Embute uma imagem à mensagem sendo renderizada.
   * 
   * @param string $image
   *   O nome do arquivo da imagem
   * 
   * @return string
   *   O identificador de conteúdo (CID) para a imagem embutida
   */
  public function embedImage(string $filename): string
  {
    if (isset($this->pathForEmbedImages)) {
      // Acrescentamos o caminho ao nosso nome da imagem
      $filename = $this->pathForEmbedImages . '/' . $filename;
    }

    return $this->message->embed(Swift_Image::fromPath($filename));
  }
}
