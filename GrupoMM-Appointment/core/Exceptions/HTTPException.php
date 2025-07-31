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
 * Uma classe para erros HTTP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Core\Helpers\InterpolateTrait;
use Exception;

class HTTPException
  extends Exception
{
  /**
   * O método para interpolar as variáveis num texto.
   */
  use InterpolateTrait;

  /**
   * Lista dos códigos de estado do HTTP.
   *
   * Obtido através de
   * http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
   *
   * @var array
   */
  private $status = array(
    400 => "Requisição inválida à '{url}'",
    401 => "Acesso não autorizado à '{url}'",
    402 => "Pagamento requerido",
    403 => "Acesso proibido à '{url}'",
    404 => "Página ou recurso '{url}' não encontrado",
    405 => "Método '{method}' não permitido",
    406 => 'Não aceitável',
    407 => 'Autenticação de proxy necessária',
    408 => 'Tempo limite da solicitação',
    409 => 'Conflito',
    410 => 'Gone',
    411 => 'Comprimento necessário',
    412 => 'Falha na pré-condição',
    413 => 'Entidade de solicitação muito grande',
    414 => 'URI de solicitação muito longa',
    415 => 'Tipo de mídia não suportado',
    416 => 'Intervalo solicitado não satisfatório',
    417 => 'Expectativa falhada',
    418 => 'I\'m a teapot', // RFC 2324
    419 => 'Tempo limite de autenticação', // not in RFC 2616
    420 => 'Falha no método', // Spring Framework
    420 => 'Enhance Your Calm', // Twitter
    422 => 'Entidade não processável', // WebDAV; RFC 4918
    423 => 'Bloqueado', // WebDAV; RFC 4918
    424 => 'Dependência com falha', // WebDAV; RFC 4918
    424 => 'Falha no método', // WebDAV)
    425 => 'Coleção desordenada', // Internet draft
    426 => 'Atualização necessária', // RFC 2817
    428 => 'Pré-requisito necessário', // RFC 6585
    429 => 'Muitas solicitações realizadas', // RFC 6585
    431 => 'Campos do cabeçalho da solicitação muito grandes', // RFC 6585
    444 => 'Sem resposta', // Nginx
    449 => 'Repetir com', // Microsoft
    450 => 'Bloqueado pelos controles dos pais do Windows', // Microsoft
    451 => 'Indisponível por motivos legais', // Internet draft
    451 => 'Redirecionar', // Microsoft
    494 => 'Cabeçalho da solicitação muito grande', // Nginx
    495 => 'Erro de certificação', // Nginx
    496 => 'Sem certificado', // Nginx
    497 => 'HTTP para HTTPS', // Nginx
    499 => 'Solicitação fechada do cliente', // Nginx
    500 => 'Erro interno do servidor',
    501 => 'Não implementado',
    502 => 'Gateway incorreto',
    503 => 'Serviço indisponível',
    504 => 'Tempo limite do gateway',
    505 => 'Versão HTTP não suportada',
    506 => 'A variante também negocia', // RFC 2295
    507 => 'Armazenamento insuficiente', // WebDAV; RFC 4918
    508 => 'Loop detectado', // WebDAV; RFC 5842
    509 => 'Limite de banda excedido', // Apache bw/limited extension
    510 => 'Não estendido', // RFC 2774
    511 => 'Autenticação de rede necessária', // RFC 6585
    598 => 'Erro de tempo limite de leitura da rede', // Unknown
    599 => 'Erro de tempo limite de conexão à rede', // Unknown
  );

  /**
   * A mensagem de exceção.
   * 
   * @var string
   */
  protected $statusPhrase;

  /**
   * O código de erro definido para esta exceção.
   * 
   * @var integer
   */
  protected $code = 0;

  /**
   * O construtor de nossa exceção.
   * 
   * @param string $url
   *   A URL requisitada
   * @param string $method
   *   O método da requisição HTTP
   * @param int $statusCode
   *   O código de estado (opcional, se nulo usará 500 como padrão)
   * @param string $statusPhrase
   *   A frase de estado (opcional, se nula usará a frase de status
   * padrão)
   */
  public function __construct(string $url, string $method,
    $statusCode = 500, $statusPhrase = null)
  {
    if (null === $statusPhrase && isset($this->status[$statusCode])) {
      $this->statusPhrase = $this->interpolate(
        $this->status[$statusCode], [
          'url' => $url,
          'method' => $method
        ]
      );
    } else {
      $this->statusPhrase = $statusPhrase;
    }

    // Armazena o código de status
    $this->code = $statusCode;

    parent::__construct($this->statusPhrase, $statusCode, null);
  }

  /**
   * Converte a exceção para string.
   * 
   * @return string
   *   A mensagem de exceção
   */
  public function __toString()
  {
    return sprintf('Erro código [%d]: %s', $this->code, $this->statusPhrase);
  }
}