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
 * Uma mensagem de e-mail.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer;

use DateTimeInterface;
use Swift_Attachment;
use Swift_Message;
use Swift_Image;

class Message
{
  /**
   * A nossa mensagem de e-mail.
   *
   * @var Swift_Message
   */
  private $message;

  /**
   * As imagens embutidas.
   *
   * @var array
   */
  private $embeddedImages = [];

  /**
   * O construtor de nossa classe.
   */
  public function __construct()
  {
    // Cria uma nova mensagem internamente
    $this->message = new Swift_Message();
  }

  /**
   * Recupera nossa mensagem de e-mail armazenada.
   * 
   * @return Swift_Message
   */
  public function getSwiftMessage()
  {
    return $this->message;
  }

  /**
   * Define um endereço de remetente.
   * 
   * @param string $address
   *   O endereço de e-mail do remetente
   * @param string $name
   *   O nome do remetente (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setFrom($address, $name = null): self
  {
    $this->message->setFrom($address, $name);
    
    return $this;
  }

  /**
   * Adiciona um endereço de destinatário à esta mensagem. Se $name for
   * passado, este nome será associado ao endereço de e-mail.
   * 
   * @param string $address
   *   O endereço de e-mail do destinatário
   * @param string $name
   *   O nome do destinatário (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function addTo($address, $name = null): self
  {
    $this->message->addTo($address, $name);

    return $this;
  }

  /**
   * Define os endereços de destinatário desta mensagem. Se a mensagem
   * precisa ser enviada à vários destinatários, uma matriz deve ser
   * usada. Exemplo:
   *   array('receiver@domain.org', 'other@domain.org' => 'Um nome')
   * Se $name for passado, este nome será associado ao endereço de
   * e-mail.
   * 
   * @param string|array $address
   *   O endereço de e-mail do destinatário (ou uma matriz com vários
   *   endereços de e-mail)
   * @param string $name
   *   O nome do destinatário (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setTo($address, $name = null): self
  {
    $this->message->setTo($address, $name);

    return $this;
  }

  /**
   * Adiciona um endereço de resposta desta mensagem. Se $name for
   * passado, este nome será associado ao endereço de e-mail.
   * 
   * @param string $address
   *   O endereço de e-mail para resposta
   * @param string $name
   *   O nome para quem deve ser respondido (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function addReplyTo($address, $name = null): self
  {
    $this->message->addReplyTo($address, $name);
    
    return $this;
  }

  /**
   * Define os endereços de resposta desta mensagem. Se a mensagem
   * precisa ser respondida à vários destinatários, uma matriz deve ser
   * usada. Exemplo:
   *   array('receiver@domain.org', 'other@domain.org' => 'Um nome')
   * Se $name for passado, este nome será associado ao endereço de
   * e-mail.
   * 
   * @param string|array $address
   *   O endereço de e-mail para resposta (ou uma matriz com vários
   *   endereços de e-mail)
   * @param string $name
   *   O nome para quem deve ser respondido (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setReplyTo($address, $name = null): self
  {
    $this->message->setReplyTo($address, $name);
    
    return $this;
  }

  /**
   * Adiciona um endereço de destinatário para quem precisa ser enviada
   * uma copia desta mensagem. Se $name for passado, este nome será
   * associado ao endereço de e-mail.
   * 
   * @param string $address
   *   O endereço de e-mail para quem devemos enviar uma cópia da
   *   mensagem
   * @param string $name
   *   O nome para quem deve ser copiado (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function addCc($address, $name = null): self
  {
    $this->message->addCc($address, $name);

    return $this;
  }

  /**
   * Define os endereços para quem devemos enviar cópias desta mensagem.
   * Se a mensagem precisa ser copiada à vários destinatários, uma
   * matriz deve ser usada. Exemplo:
   *   array('receiver@domain.org', 'other@domain.org' => 'Um nome')
   * Se $name for passado, este nome será associado ao endereço de
   * e-mail.
   * 
   * @param string|array $address
   *   O endereço de e-mail para quem devemos enviar uma cópia (ou uma
   *   matriz com vários endereços de e-mail)
   * @param string $name
   *   O nome de quem receberá a cópia (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setCc($address, $name = null): self
  {
    $this->message->setCc($address, $name);

    return $this;
  }

  /**
   * Adiciona um endereço de destinatário para quem precisa ser enviada
   * uma copia oculta desta mensagem. Se $name for passado, este nome
   * será associado ao endereço de e-mail.
   * 
   * @param string $address
   *   O endereço de e-mail para quem devemos enviar uma cópia oculta da
   *   mensagem
   * @param string $name
   *   O nome para quem deve ser copiado (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function addBcc($address, $name = null): self
  {
    $this->message->addBcc($address, $name);

    return $this;
  }

  /**
   * Define os endereços para quem devemos enviar cópias ocultas desta
   * mensagem. Se a mensagem precisa ser copiada à vários destinatários,
   * uma matriz deve ser usada. Exemplo:
   *   array('receiver@domain.org', 'other@domain.org' => 'Um nome')
   * Se $name for passado, este nome será associado ao endereço de
   * e-mail.
   * 
   * @param string|array $address
   *   O endereço de e-mail para quem devemos enviar uma cópia oculta
   *   (ou uma matriz com vários endereços de e-mail)
   * @param string $name
   *   O nome de quem receberá a cópia oculta (opcional)
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setBcc($address, $name = null): self
  {
    $this->message->setBcc($address, $name);

    return $this;
  }

  /**
   * Define um nível de prioridade.
   * 
   * @param int $priority
   *   O nível de prioridade da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setPriority(int $priority): self
  {
    $this->message->setPriority($priority);

    return $this;
  }

  /**
   * Define o assunto da mensagem.
   * 
   * @param string $subject
   *   O texto do assunto da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setSubject(string $subject): self
  {
    $this->message->setSubject($subject);

    return $this;
  }
  
  /**
   * Define o corpo da mensagem em formato HTML.
   * 
   * @param string $body
   *   O conteúdo do corpo da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setBody(string $body): self
  {
    $this->message->setBody($body, 'text/html');

    return $this;
  }

  /**
   * Define o corpo da mensagem em formato texto puro.
   * 
   * @param string $body
   *   O conteúdo do corpo da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setBodyAlternative(string $body): self
  {
    $this->message->addPart($body, 'text/plain');

    return $this;
  }

  /**
   * Define uma data para a mensagem.
   * 
   * @param DateTimeInterface $dateTime
   *   A data da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setDate(DateTimeInterface $dateTime): self
  {
    $this->message->setDate($dateTime);

    return $this;
  }

  /**
   * Permite adicionar um arquivo anexo à mensagem.
   * 
   * @param string $path
   *   O arquivo a ser anexado
   *
   * @return $this
   *   A instância do e-mail
   */
  public function attachFile(string $path): self
  {
    $this->message->attach(Swift_Attachment::fromPath($path));

    return $this;
  }

  /**
   * Permite adicionar um arquivo inline anexo à mensagem.
   * 
   * @param mixed $data
   *   O conteúdo do arquivo a ser anexado
   * @param string $filename
   *   O nome do arquivo a ser anexado
   *
   * @return $this
   *   A instância do e-mail
   */
  public function attachPDF($data, string $filename): self
  {
    // Criando o anexo com o PDF como conteúdo
    $attachment = new Swift_Attachment($data, $filename, 'application/pdf');

    $this->message->attach($attachment);

    return $this;
  }

  /**
   * Permite remover um arquivo anexado à mensagem.
   * 
   * @param string $path
   *   O arquivo a ser removido
   *
   * @return $this
   *   A instância do e-mail
   */
  public function detachFile(string $path): self
  {
    $this->message->detach(Swift_Attachment::fromPath($path));

    return $this;
  }
  
  /**
   * Adiciona uma imagem embutida na mensagem.
   * 
   * @param string $filename
   *   O nome do arquivo que contém a imagem
   * @param string $contentID
   *   O identificador de conteúdo (CID) para a imagem embutida
   */
  public function addEmbeddedImage(string $filename,
    string $contentID): void
  {
    $cid = $this->message->embed(Swift_Image::fromPath($filename));

    $this->embeddedImages[$contentID] = $cid;
  }
  
  /**
   * Recupera as informações de imagens embutidas.
   * 
   * @return array
   */
  public function getEmbeddedImages(): array
  {
    return $this->embeddedImages;
  }
}
