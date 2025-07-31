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
 * A interface para uma mensagem de e-mail.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer;

use DateTimeInterface;

interface MessageInterface
{
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
  public function setFrom($address, $name = null): self;

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
  public function addTo($address, $name = null): self;
  
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
  public function setTo($address, $name = null): self;

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
  public function addReplyTo($address, $name = null): self;

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
  public function setReplyTo($address, $name = null): self;

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
  public function addCc($address, $name = null): self;

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
  public function setCc($address, $name = null): self;

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
  public function addBcc($address, $name = null): self;

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
  public function setBcc($address, $name = null): self;
  
  /**
   * Define um nível de prioridade.
   * 
   * @param int $priority
   *   O nível de prioridade da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setPriority(int $priority): self;

  /**
   * Define o assunto da mensagem.
   * 
   * @param string $subject
   *   O texto do assunto da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setSubject(string $subject): self;
  
  /**
   * Define o corpo da mensagem em formato HTML.
   * 
   * @param string $body
   *   O conteúdo do corpo da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setBody(string $body): self;
  
  /**
   * Define o corpo da mensagem em formato texto puro.
   * 
   * @param string $body
   *   O conteúdo do corpo da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setBodyAlternative(string $body): self;
  
  /**
   * Define uma data para a mensagem.
   * 
   * @param DateTimeInterface $dateTime
   *   A data da mensagem
   *
   * @return $this
   *   A instância do e-mail
   */
  public function setDate(DateTimeInterface $dateTime): self;

  /**
   * Permite adicionar um arquivo anexo à mensagem.
   * 
   * @param string $path
   *   O arquivo a ser anexado
   *
   * @return $this
   *   A instância do e-mail
   */
  public function attachFile(string $path): self;
  
  /**
   * Permite remover um arquivo anexado à mensagem.
   * 
   * @param string $path
   *   O arquivo a ser removido
   *
   * @return $this
   *   A instância do e-mail
   */
  public function detachFile(string $path): self;

  /**
   * Adiciona uma imagem embutida na mensagem.
   * 
   * @param string $filename
   *   O nome do arquivo que contém a imagem
   * @param string $contentID
   *   O ID único que associa a imagem ao corpo do e-mail.
   */
  public function addEmbeddedImage(string $filename,
    string $contentID): void;
}
