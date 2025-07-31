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
 * Um serviço de envio de e-mail.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer;

use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Slim\Views\Twig;
use Core\Mailer\Twig\MailTemplateExtension;
use Core\Mailer\Twig\MissingBlockException;

class Mailer
{
  /**
   * O nome do host.
   *
   * @var string
   */
  protected $host = 'localhost';

  /**
   * O número da porta utilizada para conexão.
   *
   * @var integer
   */
  protected $port = 25;

  /**
   * O nome do usuário utilizado na autenticação.
   *
   * @var string
   */
  protected $username = '';

  /**
   * A senha do usuário utilizada na autenticação.
   *
   * @var string
   */
  protected $password = '';

  /**
   * O protocolo a ser utilizado.
   *
   * @var string|null
   */
  protected $protocol = null;

  /**
   * As opções de configuração da conexão com o servidor SMTP.
   *
   * @var array|null
   */
  protected $stream;
  
  /**
   * O serviço de envio Swift Mailer.
   *
   * @var Swift_Mailer
   */
  protected $swiftMailer;
  
  /**
   * A engine de renderização de templates Twig.
   *
   * @var Twig
   */
  protected $twig;
  
  /**
   * Os dados do remetente.
   *
   * @var array
   */
  protected $from = [];

  /**
   * O caminho onde estão armazenadas as imagens a serem embutidas.
   *
   * @var string
   */
  protected $pathForEmbedImages;
  
  /**
   * O construtor de nossa classe.
   * 
   * @param Twig $twig
   *   O sistema de templates Twig
   * @param array $settings opcional
   *   As configurações para o mailer
   */
  public function __construct(Twig $twig, array $settings = [])
  {
    // Analise as configurações, atualizando as propriedades do mailer
    foreach ($settings as $key => $value) {
      if (property_exists($this, $key)) {
        $this->{$key} = $value;
      }
    }
    
    // Inicializa as configurações para o servidor de SMTP
    $transport = new Swift_SmtpTransport($this->host, $this->port,
      $this->protocol);
    $transport->setUsername($this->username);
    $transport->setPassword($this->password);

    // Adiciona as configurações de stream, se necessário
    if (isset($this->stream)) {
      $transport->setStreamOptions($this->stream);
    }
    
    // Cria o componente Swift usando as configurações do servidor de
    // SMTP
    $this->swiftMailer = new Swift_Mailer($transport);

    // Associa nosso renderizador de templates
    $this->twig = $twig;
  }
  
  /**
   * Define o remetente padrão.
   * 
   * @param string $address
   *   O endereço de e-mail
   * @param string $name
   *   O nome atrelado ao endereço (opcional)
   */
  public function setDefaultFrom(string $address,
    string $name = ''): void
  {
    $this->from = compact('address', 'name');
  }
  
  /**
   * Define o local onde estão armazenadas as imagens embutidas.
   * 
   * @param string $path
   *   O caminho para o local de armazenamento
   */
  public function setPathForEmbedImages(string $path): void
  {
    $this->pathForEmbedImages = rtrim($path, '/');
  }
  
  /**
   * Envia uma nova mensagem de e-mail utilizando um template Twig.
   * 
   * @param string $templateName
   *   O nome do template que irá ter o com o conteúdo do e-mail
   * @param array $data [opcional]
   *   Os dados variáveis para renderizar o template
   * @param callable $callback [opcional]
   *   Uma função a ser chamada antes do envio, e na qual fazemos
   *   tratamentos adicionais
   * @param array $attachments
   *   Os arquivos a serem anexados ao e-mail
   *
   * @return bool
   *   O resultado do envio da mensagem
   */
  public function send(string $templateName, array $data = [],
    callable $callback = null, array $attachments = []): bool
  {
    // Verifica se o nome do template foi fornecido
    if (empty($templateName)) {

      return false;
    }

    // Criamos uma nova mensagem
    $message = new Message();

    // Determinamos o endereço do remetente
    $message->setFrom($this->from['address'], $this->from['name']);

    // Se temos um callback, então executa-o, pois é nele em que
    // permitimos a inclusão de imagens embutidas, arquivos anexos e
    // podemos também definir os endereços do destinatário e demais
    // opções do e-mail
    if (is_callable($callback)) {
      call_user_func($callback, $message);
    }

    // Extraímos dos dados possíveis valores para os parâmetros do nosso
    // e-mail
    if (array_key_exists('To', $data)) {
      if ( array_key_exists("email", $data['To']) &&
           array_key_exists("name", $data['To']) ) {
        // Definimos o destinatário único
        $message->setTo($data['To']['email'], $data['To']['name']);
      } else {
        // Definimos cada destinatário separadamente
        foreach ($data['To'] as $key => $mail) {
          // Adicionamos o destinatário
          if (array_key_exists('name', $mail)) {
            $message->addTo($mail['email'], $mail['name']);
          } else {
            $message->addTo($mail['email']);
          }
        }
      }
    }
    if (array_key_exists('ReplyTo', $data)) {
      if ( array_key_exists("email", $data['ReplyTo']) &&
           array_key_exists("name", $data['ReplyTo']) ) {
        // Definimos o destinatário único
        $message->setReplyTo($data['ReplyTo']['email'],
          $data['ReplyTo']['name'])
        ;
      } else {
        // Definimos cada destinatário separadamente
        foreach ($data['ReplyTo'] as $key => $mail) {
          // Adicionamos o destinatário
          $message->addReplyTo($mail['email'], $mail['name']);
        }
      }
    }
    if (array_key_exists('Cc', $data)) {
      if ( array_key_exists("email", $data['Cc']) &&
           array_key_exists("name", $data['Cc']) ) {
        // Definimos o destinatário único
        $message->setCc($data['Cc']['email'],
          $data['Cc']['name'])
        ;
      } else {
        // Definimos cada destinatário separadamente
        foreach ($data['Cc'] as $key => $mail) {
          // Adicionamos o destinatário
          $message->addCc($mail['email'], $mail['name']);
        }
      }
    }
    if (array_key_exists('Bcc', $data)) {
      if ( array_key_exists("email", $data['Bcc']) &&
           array_key_exists("name", $data['Bcc']) ) {
        // Definimos o destinatário único
        $message->setBcc($data['Bcc']['email'],
          $data['Bcc']['name'])
        ;
      } else {
        // Definimos cada destinatário separadamente
        foreach ($data['Bcc'] as $key => $mail) {
          // Adicionamos o destinatário
          $message->addBcc($mail['email'], $mail['name']);
        }
      }
    }

    // Renderizamos nossa mensagem usando o template informado
    
    // Primeiramente, recuperamos o ambiente Twig
    $twigEnvironment = $this->twig->getEnvironment();

    // Adicionamos a extensão de renderização de E-mails, se necessário
    if (!$twigEnvironment->hasExtension(MailTemplateExtension::class)) {
      $twigEnvironment->addExtension(new MailTemplateExtension());
    }

    // Definimos nosso interpretador de templates de e-mail baseado no
    // Twig
    $twigTemplate = $twigEnvironment->getExtension(MailTemplateExtension::class);
    
    // Passamos para a extensão Twig os dados da mensagem de e-mail que
    // estamos compondo. Caso existam imagens embutidas, nossa mensagem
    // será atualizada com estas informações
    $twigTemplate->setSwiftMessage($message->getSwiftMessage());
    
    // Determinamos o caminho onde estão as imagens embutidas
    $twigTemplate->setPathForEmbedImages($this->pathForEmbedImages);

    // Carrega o template
    $template = $twigEnvironment->load($templateName);

    // Carregamos os dados variáveis
    $context = $twigEnvironment->mergeGlobals($data);

    // Analisamos nosso template
    
    // Verifica se temos um bloco de assunto. Se nosso template não
    // tiver o bloco 'Assunto', e não for passado como parâmetro, então
    // dispara a exceção
    if ( !$template->hasBlock('subject', $context) ) {
      if (array_key_exists('Subject', $data)) {
        // Definimos o assunto da mensagem através dos dados
        $message->setSubject($data['Subject']);
      } else {
        throw MissingBlockException::missingBlock($template->getBlockNames($context, []));
      }
    }

    // Verifica se temos um corpo do e-mail. Se nosso template não tiver
    // um bloco 'Corpo HTML' nem um bloco 'Corpo Texto', dispara a
    // exceção
    if ( !$template->hasBlock('body_html', $context) &&
         !$template->hasBlock('body_text', $context) ) {
      throw MissingBlockException::missingBlock($template->getBlockNames($context, []));
    }

    // Renderizamos o assunto
    $subject = $template->renderBlock('subject', $data);
    $message->setSubject($subject);
    
    // Recupera as informações de imagens embutidas, se definidas
    // durante a execução do callback. Imagens embutidas também podem
    // ser definidas através do template Twig
    $embeddedImages = $message->getEmbeddedImages();
    if (count($embeddedImages) > 0) {
      // Adiciona as informações de imagens embutidas nos dados a serem
      // utilizados na renderização, permitindo associar os CID's das
      // imagens corretamente
      $data['embeddedImages'] = [];
      foreach ($embeddedImages as $key => $value) {
        $data['embeddedImages'][$key] = $value;
      }
    }

    // Verifica se o nosso template tem o bloco 'Corpo HTML'
    if ($template->hasBlock('body_html', $context)) {
      $bodyHtml = $template->renderBlock('body_html', $data);

      // Verifica se temos o bloco para o conteúdo do e-mail em texto
      // puro
      if (!$template->hasBlock('body_text', $context)) {
        $html2text = new html2text();
        $bodyText = $html2text->convert($bodyHtml);
      } else {
        $bodyText = $template->renderBlock('body_text', $data);
      }

      $swiftMessage = $twigTemplate->getSwiftMessage();
      $swiftMessage->setBody($bodyHtml, 'text/html');
      $swiftMessage->addPart($bodyText, 'text/plain');
    } else {
      $bodyText = $template->renderBlock('body_text', $data);
      $swiftMessage = $twigTemplate->getSwiftMessage();

      $swiftMessage->setBody($bodyText, 'text/plain');
    }

    // Enviamos a mensagem e retornamos o resultado
    return $this->swiftMailer->send($swiftMessage);
  }
}
