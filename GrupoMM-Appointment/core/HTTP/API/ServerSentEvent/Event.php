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
 * Uma classe que define um evento a ser enviado para o navegador usando
 * a API Server Sent Event - SSE. Com SSE, o servidor pode enviar dados
 * para uma página a qualquer momento na forma de mensagens, que são
 * recebidas automaticamente pelo navegador, e que podem ser acessadas
 * dentro do JavaScript através de uma API chamada EventSource.
 *
 * Esta classe encapsula uma mensagem (ou evento) que o servidor irá
 * enviar, formatando seu conteúdo dentro do padrão exigido por esta
 * API.
 */

namespace Core\HTTP\API\ServerSentEvent;

class Event
{
  /**
   * Comentários do evento.
   *
   * @var array
   */
  private $comments = [];

  /**
   * A identificação única do evento.
   *
   * @var int
   */
  private $id;

  /**
   * O tipo de evento.
   *
   * @var string
   */
  private $eventType;

  /**
   * O intervalo entre tentativas de reconexão em caso de erros.
   *
   * @var int
   */
  private $retry;

  /**
   * Os dados a serem enviados com o evento.
   *
   * @var array
   */
  private $data = [];

  /**
   * Adiciona um comentário ao evento.
   *
   * @param string $comment
   *   O comentário a ser adicionado
   * 
   * @return $this
   *   A instância do evento
   */
  public function addComment(string $comment): self
  {
    $this->comments = array_merge(
      $this->comments,
      $this->extractNewlines($comment)
    );

    return $this;
  }

  /**
   * Define uma identificação única usado para definir a sequência dos
   * eventos. Ao informar um ID, o navegador sabe qual foi o ultimo
   * evento disparado, e automaticamente envia um header Last-Event-Id
   * na tentativa de reconexão. O servidor pode usar este header para
   * saber quais dados deve enviar para o cliente (por exemplo, em qual
   * ponto do feed estava).
   *
   * @param int $id
   *   A identificação única deste evento
   * 
   * @return $this
   *   A instância do evento
   */
  public function setId(int $id): self
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Especifica o tipo de evento. Um fluxo de eventos pode ter tipos de
   * eventos distintamente diferentes. Isso é opcional e pode ser útil
   * para processar eventos no cliente.
   *
   * @param string $event
   *   O tipo de evento
   * 
   * @return $this
   *   A instância do evento
   */
  public function setEventType(string $eventType): self
  {
    $this->eventType = $eventType;

    return $this;
  }

  /**
   * Define o intervalo entre tentativas de reconexão em caso de erros.
   * O navegador tenta reconectar automaticamente respeitando esse
   * valor.
   *
   * @param int $retry
   *   O tempo (em milissegundos)
   * 
   * @return $this
   *   A instância do evento
   */
  public function setRetry(int $retry): self
  {
    $this->retry = $retry;

    return $this;
  }

  /**
   * Define o conteúdo dos dados a serem enviados com o evento. Quando
   * for retornado, vai acionar o evento onMessage que foi definido no
   * cliente. Esse texto pode ser qualquer tipo de dado em formato
   * texto: uma string, números, JSON, XML, etc.
   *
   * @param string $data
   *   Os dados a serem enviados
   * 
   * @return $this
   *   A instância do evento
   */
  public function setData(string $data): self
  {
    $this->data = $this->extractNewlines($data);

    return $this;
  }

  /**
   * Anexa novos dados a serem enviados com o evento.
   *
   * @param string $data
   *   Os dados a serem anexados
   * 
   * @return $this
   *   A instância do evento
   */
  public function appendData(string $data): self
  {
    $this->data = array_merge(
      $this->data,
      $this->extractNewlines($data)
    );

    return $this;
  }

  /**
   * Converte o evento para texto, envia para o buffer de saída e
   * descarrega o conteúdo no buffer para ser enviado ao cliente.
   *
   * @return void
   */
  public function flush(): void
  {
    $response = $this->getFormattedComments()
      . $this->getFormattedId()
      . $this->getFormattedEvent()
      . $this->getFormattedRetry()
      . $this->getFormattedData()
    ;

    print '' !== $response
      ? $response . PHP_EOL
      : ''
    ;

    // Envia o buffer de saída para o cliente    
    ob_flush();
    flush();
  }

  /**
   * Obtém os comentários formatados.
   *
   * @return string
   */
  public function getFormattedComments(): string
  {
    return $this->formatLines('', $this->comments);
  }

  /**
   * Obtém o ID formatado.
   *
   * @return string
   */
  public function getFormattedId(): string
  {
    return $this->formatLines('id', $this->id);
  }

  /**
   * Obtém o evento formatado.
   *
   * @return string
   */
  public function getFormattedEvent(): string
  {
    return $this->formatLines('event', $this->eventType);
  }

  /**
   * Obtém o tempo para nova tentativa de reconexão formatado.
   *
   * @return string
   */
  public function getFormattedRetry(): string
  {
    return $this->formatLines('retry', $this->retry);
  }

  /**
   * Obtém os dados formatados.
   *
   * @return string
   */
  public function getFormattedData(): string
  {
    return $this->formatLines('data', $this->data);
  }

  /**
   * Extrai da string as linhas, quando utilizado o caractere de retorno
   * de linha.
   *
   * @param string $input
   *   A entrada de cujo conteúdo será extraído as linhas
   *
   * @return array
   */
  private function extractNewlines(string $input): array
  {
    return explode(PHP_EOL, $input);
  }

  /**
   * Formata as linhas.
   *
   * @param string $key
   *   A chave desta linha
   * @param mixed $lines
   *   Os conteúdos desta linha
   *
   * @return string
   */
  private function formatLines(string $key, $lines): string
  {
    $formatted = array_map(
      function ($line) use ($key) {
        $key = '' !== $key
          ? $key . ': '
          : ''
        ;
        return $key . $line . PHP_EOL;
      },
      (array) $lines
    );

    return implode('', $formatted);
  }

  /**
   * Cria um novo evento.
   *
   * @return $this
   *   A instância do evento
  */
  public static function create(): self
  {
    return new static();
  }
}