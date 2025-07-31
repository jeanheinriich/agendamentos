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
 * As constantes nomeadas para códigos de status do protocolo HTTP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\HTTP;

class HTTPStatusCodes {

  // [Informativo 1xx]
  const HTTP_CONTINUE                        = 100;
  const HTTP_SWITCHING_PROTOCOLS             = 101;

  // [Confirmação 2xx]
  const HTTP_OK                              = 200;
  const HTTP_CREATED                         = 201;
  const HTTP_ACCEPTED                        = 202;
  const HTTP_NONAUTHORITATIVE_INFORMATION    = 203;
  const HTTP_NO_CONTENT                      = 204;
  const HTTP_RESET_CONTENT                   = 205;
  const HTTP_PARTIAL_CONTENT                 = 206;

  // [Redirecionamento 3xx]
  const HTTP_MULTIPLE_CHOICES                = 300;
  const HTTP_MOVED_PERMANENTLY               = 301;
  const HTTP_FOUND                           = 302;
  const HTTP_SEE_OTHER                       = 303;
  const HTTP_NOT_MODIFIED                    = 304;
  const HTTP_USE_PROXY                       = 305;
  const HTTP_UNUSED                          = 306;
  const HTTP_TEMPORARY_REDIRECT              = 307;

  // [Erro do cliente 4xx]
  const HTTP_BAD_REQUEST                     = 400;
  const HTTP_UNAUTHORIZED                    = 401;
  const HTTP_PAYMENT_REQUIRED                = 402;
  const HTTP_FORBIDDEN                       = 403;
  const HTTP_NOT_FOUND                       = 404;
  const HTTP_METHOD_NOT_ALLOWED              = 405;
  const HTTP_NOT_ACCEPTABLE                  = 406;
  const HTTP_PROXY_AUTHENTICATION_REQUIRED   = 407;
  const HTTP_REQUEST_TIMEOUT                 = 408;
  const HTTP_CONFLICT                        = 409;
  const HTTP_GONE                            = 410;
  const HTTP_LENGTH_REQUIRED                 = 411;
  const HTTP_PRECONDITION_FAILED             = 412;
  const HTTP_REQUEST_ENTITY_TOO_LARGE        = 413;
  const HTTP_REQUEST_URI_TOO_LONG            = 414;
  const HTTP_UNSUPPORTED_MEDIA_TYPE          = 415;
  const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
  const HTTP_EXPECTATION_FAILED              = 417;

  // [Erro de servidor 5xx]
  const HTTP_INTERNAL_SERVER_ERROR           = 500;
  const HTTP_NOT_IMPLEMENTED                 = 501;
  const HTTP_BAD_GATEWAY                     = 502;
  const HTTP_SERVICE_UNAVAILABLE             = 503;
  const HTTP_GATEWAY_TIMEOUT                 = 504;
  const HTTP_VERSION_NOT_SUPPORTED           = 505;

  private static $messages = array(
    // [Informativo 1xx]
    100 => '100 Continuar',
    101 => '101 Mudando Protocolos',
    102 => '102 Processando',

    // [Confirmação 2xx]
    200 => '200 Ok',
    201 => '201 Criado',
    202 => '202 Aceito',
    203 => '203 Não autorizado',
    204 => '204 Nenhum conteúdo',
    205 => '205 Resetar conteúdo',
    206 => '206 Conteúdo parcial',

    // [Redirecionamento 3xx]
    300 => '300 Múltipla escolha',
    301 => '301 Movido permanentemente',
    302 => '302 Encontrado',
    303 => '303 Veja outro',
    304 => '304 Não modificado',
    305 => '305 Use Proxy',
    306 => '306 Proxy trocado',
    307 => '307 Redirecionado temporariamente',

    // [Erro do cliente 4xx]
    400 => '400 Solicitação inválida',
    401 => '401 Não autorizado',
    402 => '402 Pagamento necessário',
    403 => '403 Proibido',
    404 => '404 Não encontrado',
    405 => '405 Método não permitido',
    406 => '406 Não aceito',
    407 => '407 Autenticação de Proxy necessária',
    408 => '408 Tempo de solicitação esgotado',
    409 => '409 Conflito',
    410 => '410 Perdido',
    411 => '411 Duração necessária',
    412 => '412 Falha de pré-condição',
    413 => '413 Solicitação da entidade muito extensa',
    414 => '414 Solicitação de URL muito longa',
    415 => '415 Tipo de mídia não suportado',
    416 => '416 Solicitação de faixa não satisfatória',
    417 => '417 Falha na expectativa',

    // [Erro de servidor 5xx]
    500 => '500 Erro interno do servidor',
    501 => '501 Não implementado',
    502 => '502 Porta de entrada ruim',
    503 => '503 Serviço indisponível',
    504 => '504 Tempo limite da porta de entrada',
    505 => '505 Versão HTTP não suportada'
  );

  /**
   * Obtém o cabeçalho HTTP para um código informado.
   *
   * @param int $code
   *   O código de status HTTP
   *
   * @return string
   *   O cabeçalho
   */
  public static function httpHeaderFor(int $code): string
  {
    return trim(
      "HTTP/1.1 {$code} " . self::getMessageForCode($code)
    );
  }

  /**
   * Obtém a mensagem HTTP para um código informado.
   *
   * @param int $code
   *   O código de status HTTP
   *
   * @return string
   *   A mensagem referente ao código
   */
  public static function getMessageForCode(int $code): string
  {
    return (array_key_exists($code, self::$messages))
      ? self::$messages[$code]
      : ''
    ;
  }

  /**
   * Obtém se um determinado código de status pode ser considerado um
   * erro.
   *
   * @param int $code
   *   O código de status HTTP
   *
   * @return boolean
   */
  public static function isError(int $code): bool
  {
    return is_numeric($code) && $code >= self::HTTP_BAD_REQUEST;
  }

  /**
   * Determina se temos conteúdo na resposta em função do código de
   * status informado.
   *
   * @param int $code
   *   O código de status HTTP
   *
   * @return boolean
   */
  public static function canHaveBody(int $code): bool
  {
    return
      // True if not in 100s
      ($code < self::HTTP_CONTINUE || $code >= self::HTTP_OK)
      && // and not 204 NO CONTENT
      $code != self::HTTP_NO_CONTENT
      && // and not 304 NOT MODIFIED
      $code != self::HTTP_NOT_MODIFIED
    ;
  }
}