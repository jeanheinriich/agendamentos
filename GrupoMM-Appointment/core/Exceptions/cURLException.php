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
 * Um invólucro para permitir gerar os erros de exceção para a extensão
 * cURL, com as mensagens traduzidas para o português. Os valores foram
 * obtidos em https://curl.haxx.se/libcurl/c/libcurl-errors.html
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Core\Helpers\InterpolateTrait;
use Exception;

class cURLException
  extends Exception
{
  /**
   * O método para interpolar as variáveis num texto
   */
  use InterpolateTrait;

  /**
   * Lista dos códigos de erro do cURL
   *
   * @var array
   */
  private $errorCodes = [
    1 => [
        "CURLE_UNSUPPORTED_PROTOCOL",
        "A URL '{url}' usa um protocolo não suportado."
      ],
    2 => [
        "CURLE_FAILED_INIT",
        "Falha na inicialização do CURL."
      ],
    3 => [
        "CURLE_URL_MALFORMAT",
        "A URL '{url}' não foi formatada corretamente."
      ],
    4 => [
        "CURLE_NOT_BUILT_IN",
        "Uma característica, protocolo ou opção solicitada não foi "
        . "encontrada incorporada nesta libcurl devido a uma decisão "
        . "de tempo de construção da própria biblioteca."
      ],
    5 => [
        "CURLE_COULDNT_RESOLVE_PROXY",
        "Não foi possível resolver o proxy."
      ],
    6 => [
        "CURLE_COULDNT_RESOLVE_HOST",
        "Não foi possível resolver o host para o endereço '{url}'."
      ],
    7 => [
        "CURLE_COULDNT_CONNECT",
        "Não foi possível conectar ao host ou proxy."
      ],
    8 => [
        "CURLE_WEIRD_SERVER_REPLY",
        "O servidor enviou dados que a libcurl não pôde analisar."
      ],
    9 => [
        "CURLE_REMOTE_ACCESS_DENIED",
        "Foi-nos negado o acesso ao recurso dado na URL '{url}'."
      ],
    10 => [
        "CURLE_FTP_ACCEPT_FAILED",
        "Enquanto esperava que o servidor se conectasse de volta "
        . "quando uma sessão FTP ativa fosse utilizada, um código de "
        . "erro foi enviado sobre a conexão de controle ou similar."
      ],
    11 => [
        "CURLE_FTP_WEIRD_PASS_REPLY",
        "Após o envio da senha para o servidor FTP, a libcurl "
        . "espera uma resposta adequada, porém um código inesperado "
        . "foi devolvido."
      ],
    12 => [
        "CURLE_FTP_ACCEPT_TIMEOUT",
        "Durante uma sessão FTP ativa enquanto se espera que o servidor "
        . "se conecte, o tempo limite CURLOPT_ACCEPTTIMEOUT_MS (ou o "
        . "padrão interno) expirou."
      ],
    13 => [
        "CURLE_FTP_WEIRD_PASV_REPLY",
        "A libcurl não conseguiu obter um resultado sensato do "
        . "servidor como resposta a um comando PASV ou EPSV. O "
        . "servidor está com falhas."
      ],
    14 => [
        "CURLE_FTP_WEIRD_227_FORMAT",
        "Os servidores FTP retornam uma linha 227 como resposta a um "
        . "comando PASV. Se a libcurl não conseguir analisar essa "
        . "linha, este código de retorno é passado de volta."
      ],
    15 => [
        "CURLE_FTP_CANT_GET_HOST",
        "Uma falha interna na procura do host utilizado para a nova "
        . "conexão."
      ],
    16 => [
        "CURLE_HTTP2",
        "Foi detectado um problema na camada de enquadramento do HTTP2. "
        . "Isto é um tanto genérico e pode ser um entre vários "
        . "problemas, consulte o buffer de erros para obter detalhes."
      ],
    17 => [
        "CURLE_FTP_COULDNT_SET_TYPE",
        "Recebeu um erro ao tentar configurar o modo de transferência "
        . "para binário ou ASCII."
      ],
    18 => [
        "CURLE_PARTIAL_FILE",
        "Uma transferência de arquivo foi mais curta ou maior do que "
        . "o esperado. Isto acontece quando o servidor primeiro relata "
        . "um tamanho de transferência esperado, e depois entrega "
        . "dados que não correspondem ao tamanho previamente "
        . "determinado."
      ],
    19 => [
        "CURLE_FTP_COULDNT_RETR_FILE",
        "Esta foi ou uma resposta estranha a um comando 'RETR' ou uma "
        . "transferência de zero bytes completa."
      ],
    20 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    21 => [
        "CURLE_QUOTE_ERROR",
        "Ao enviar comandos 'QUOTE' personalizados para o servidor "
        . "remoto, um dos comandos retornou um código de erro que era "
        . "400 ou superior (para FTP) ou indicava a conclusão sem "
        . "sucesso do comando."
      ],
    22 => [
        "CURLE_HTTP_RETURNED_ERROR",
        "HTTP retornou um erro. Isto é retornado se CURLOPT_FAILONERROR "
        . "for definido VERDADEIRO e o servidor HTTP retornar um código "
        . "de erro que é >= 400."
      ],
    23 => [
        "CURLE_WRITE_ERROR",
        "Ocorreu um erro ao escrever dados recebidos em um arquivo "
        . "local, ou um erro foi devolvido à libcurl a partir de uma "
        . "chamada de retorno de escrita."
      ],
    24 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    25 => [
        "CURLE_UPLOAD_FAILED",
        "Falha ao iniciar o upload. Para uma comunicação por FTP, isto "
        . "indica que o servidor negou o comando STOR. O buffer de "
        . "erros geralmente contém a explicação do servidor para isso."
      ],
    26 => [
        "CURLE_READ_ERROR",
        "Houve um problema na leitura de um arquivo local ou um erro "
        . "devolvido pela chamada de retorno de leitura."
      ],
    27 => [
        "CURLE_OUT_OF_MEMORY",
        "Falta de memória."
      ],
    28 => [
        "CURLE_OPERATION_TIMEDOUT",
        "Tempo limite de espera da operação esgotado. O tempo de "
        . "espera especificado foi alcançado de acordo com as "
        . "condições."
      ],
    29 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    30 => [
        "CURLE_FTP_PORT_FAILED",
        "O comando FTP PORT retornou erro. Isto acontece ."
        . "principalmente quando não se especifica um endereço "
        . "suficientemente bom para a libcurl usar. Consulte "
        . "CURLOPT_FTPPORT."
      ],
    31 => [
        "CURLE_FTP_COULDNT_USE_REST",
        "O comando FTP REST retornou erro. Isto nunca deve acontecer "
        . "se o servidor estiver funcionando corretamente."
      ],
    32 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    33 => [
        "CURLE_RANGE_ERROR",
        "O servidor não suporta ou aceita solicitações de 'range'."
      ],
    34 => [
        "CURLE_HTTP_POST_ERROR",
        "Erro no envio por HTTP Post. Este é um erro estranho que "
        . "ocorre principalmente devido à confusão interna no cURL."
      ],
    35 => [
        "CURLE_SSL_CONNECT_ERROR",
        "Ocorreu um problema em algum lugar no aperto de mão SSL/TLS. "
        . "Para saber mais detalhes, leia o buffer de erros, pois ele "
        . "aponta o problema um pouco melhor. Podem ser certificados "
        . "(formatos de arquivo, caminhos, permissões), senhas, e "
        . "outros."
      ],
    36 => [
        "CURLE_BAD_DOWNLOAD_RESUME",
        "O download não pôde ser retomado porque a compensação "
        . "especificada estava fora dos limites do arquivo."
      ],
    37 => [
        "CURLE_FILE_COULDNT_READ_FILE",
        "Um arquivo especificado com FILE:// não pôde ser aberto. "
        . "Muito provavelmente porque o caminho do arquivo não "
        . "identifica um arquivo existente. Verifique as permissões "
        . "dos arquivos."
      ],
    38 => [
        "CURLE_LDAP_CANNOT_BIND",
        "O LDAP não pode vincular. A operação de ligação do LDAP "
        . "falhou."
      ],
    39 => [
        "CURLE_LDAP_SEARCH_FAILED",
        "A pesquisa LDAP falhou."
      ],
    40 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    41 => [
        "CURLE_FUNCTION_NOT_FOUND",
        "Função não encontrada. Uma função zlib necessária não foi "
        . "encontrada."
      ],
    42 => [
        "CURLE_ABORTED_BY_CALLBACK",
        "Abortado pelo callback. Uma chamada de retorno retornou "
        . "'abort' para a libcurl."
      ],
    43 => [
        "CURLE_BAD_FUNCTION_ARGUMENT",
        "Uma função foi chamada com um parâmetro ruim."
      ],
    44 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    45 => [
        "CURLE_INTERFACE_FAILED",
        "Erro de interface. Uma interface de saída especificada não "
        . "pôde ser usada. Defina qual interface usar para o endereço "
        . "IP de origem das conexões de saída com CURLOPT_INTERFACE."
      ],
    46 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    47 => [
        "CURLE_TOO_MANY_REDIRECTS",
        "Demasiados redirecionamentos. Ao seguir os ."
        . "redirecionamentos, a libcurl atingiu a quantidade máxima. "
        . "Defina seu limite com CURLOPT_MAXREDIRS."
      ],
    48 => [
        "CURLE_UNKNOWN_OPTION",
        "Uma opção passada para a libcurl não é reconhecida/conhecida. "
        . "Consulte a documentação apropriada. Isto é muito "
        . "provavelmente um problema no programa que usa a libcurl. O "
        . "buffer de erros pode conter informações mais específicas "
        . "sobre qual opção exata ela diz respeito."
      ],
    49 => [
        "CURLE_TELNET_OPTION_SYNTAX",
        "Uma string de opção telnet foi formatada ilegalmente."
      ],
    50 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    51 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    52 => [
        "CURLE_GOT_NOTHING",
        "Nada foi devolvido do servidor e, dadas as circunstâncias, "
        . "não receber nada é considerado um erro."
      ],
    53 => [
        "CURLE_SSL_ENGINE_NOTFOUND",
        "O motor criptográfico especificado não foi encontrado."
      ],
    54 => [
        "CURLE_SSL_ENGINE_SETFAILED",
        "Falha ao definir o motor de criptografia SSL selecionado "
        . "como padrão!"
      ],
    55 => [
        "CURLE_SEND_ERROR",
        "Falha no envio de dados da rede."
      ],
    56 => [
        "CURLE_RECV_ERROR",
        "Falha no recebimento de dados da rede."
      ],
    57 => [
        "UNDEFINED",
        "Erro desconhecido ou não definido."
      ],
    58 => [
        "CURLE_SSL_CERTPROBLEM",
        "Problema no certificado SSL."
      ],
    59 => [
        "CURLE_SSL_CIPHER",
        "Problema na chave SSL. Não foi possível usar as cifras "
        . "especificadas."
      ],
    60 => [
        "CURLE_PEER_FAILED_VERIFICATION",
        "Problema no CACERT SSL. O certificado SSL do servidor "
        . "remoto ou a impressão digital SSH md5 não foi considerado "
        . "OK."
      ],
    61 => [
        "CURLE_BAD_CONTENT_ENCODING",
        "Codificação de transferência não reconhecida."
      ],
    62 => [
        "CURLE_LDAP_INVALID_URL",
        "A URL '{url}' LDAP é inválida."
      ],
    63 => [
        "CURLE_FILESIZE_EXCEEDED",
        "Tamanho máximo de arquivo excedido."
      ],
    64 => [
        "CURLE_USE_SSL_FAILED",
        "O nível FTP SSL solicitado falhou."
      ],
    65 => [
        "CURLE_SEND_FAIL_REWIND",
        "Ao fazer uma operação de envio, os dados tiveram que ser "
        . "'rebobinados' para retransmissão, mas a operação de "
        . "'rebobinagem' falhou."
      ],
    66 => [
        "CURLE_SSL_ENGINE_INITFAILED",
        "A inicialização do motor SSL falhou."
      ],
    67 => [
        "CURLE_LOGIN_DENIED",
        "O servidor remoto negou o acesso ao cURL."
      ],
    68 => [
        "CURLE_TFTP_NOTFOUND",
        "Arquivo não encontrado no servidor TFTP."
      ],
    69 => [
        "CURLE_TFTP_PERM",
        "Problema de permissão no servidor TFTP."
      ],
    70 => [
        "CURLE_REMOTE_DISK_FULL",
        "Espaço em disco esgotado no servidor."
      ],
    71 => [
        "CURLE_TFTP_ILLEGAL",
        "Operação TFTP ilegal."
      ],
    72 => [
        "CURLE_TFTP_UNKNOWNID",
        "ID de transferência TFTP desconhecida."
      ],
    73 => [
        "CURLE_REMOTE_FILE_EXISTS",
        "O arquivo já existe no servidor e não pode ser sobrescrito."
      ],
    74 => [
        "CURLE_TFTP_NOSUCHUSER",
        "Este erro nunca deve ser devolvido por um servidor TFTP que "
        . "funcione corretamente."
      ],
    75 => [
        "CURLE_CONV_FAILED",
        "A conversão de caracteres falhou."
      ],
    76 => [
        "CURLE_CONV_REQD",
        "O chamador deve registrar as chamadas de retorno de "
        . "conversão."
      ],
    77 => [
        "CURLE_SSL_CACERT_BADFILE",
        "Problema com a leitura do certificado SSL CA."
      ],
    78 => [
        "CURLE_REMOTE_FILE_NOT_FOUND",
        "O recurso referenciado na URL '{url}' não existe."
      ],
    79 => [
        "CURLE_SSH",
        "Um erro não especificado ocorreu durante a sessão SSH."
      ],
    80 => [
        "CURLE_SSL_SHUTDOWN_FAILED",
        "Falha no desligamento da conexão SSL."
      ],
    81 => [
        "CURLE_AGAIN",
        "O soquete não está pronto para envio/recuperação, espere "
        . "até estar pronto e tente novamente. Este código de retorno "
        . "só é devolvido de curl_easy_recv e curl_easy_send"
      ],
    82 => [
        "CURLE_SSL_CRL_BADFILE",
        "Falha no carregamento do arquivo CRL"
      ],
    83 => [
        "CURLE_SSL_ISSUER_ERROR",
        "A conferência do emissor falhou."
      ],
    84 => [
        "CURLE_FTP_PRET_FAILED",
        "O servidor FTP não entende nada do comando PRET ou não "
        . "apóia o argumento dado. Tenha cuidado ao utilizar "
        . "CURLOPT_CUSTOMREQUEST, um comando LIST personalizado será "
        . "enviado com o comando PRET CMD também antes do PASV."
      ],
    85 => [
        "CURLE_RTSP_CSEQ_ERROR",
        "Incompatibilidade dos números CSeq de RTSP."
      ],
    86 => [
        "CURLE_RTSP_SESSION_ERROR",
        "Incompatibilidade dos identificadores de sessão RTSP."
      ],
    87 => [
        "CURLE_FTP_BAD_FILE_LIST",
        "Impossível analisar a lista de arquivos FTP (durante o "
        . "download de curingas FTP)."
      ],
    88 => [
        "CURLE_CHUNK_FAILED",
        "Incompatibilidade dos identificadores de sessão RTSP."
      ],
    89 => [
        "CURLE_NO_CONNECTION_AVAILABLE",
        "Sem conexão disponível, a sessão será enfileirada. "
        . "(Somente para uso interno, nunca será devolvido pela "
        . "libcurl)"
      ],
    90 => [
        "CURLE_SSL_PINNEDPUBKEYNOTMATCH",
        "Falha na correspondência da chave com alfinete "
        . "especificada com CURLOPT_PINNEDPUBLICKEY."
      ],
    91 => [
        "CURLE_SSL_INVALIDCERTSTATUS",
        "Status devolveu falha quando solicitado com "
        . "CURLOPT_SSL_VERIFYSTATUS."
      ],
    92 => [
        "CURLE_HTTP2_STREAM",
        "Erro de fluxo (stream) na camada de enquadramento do HTTP/2."
      ],
    93 => [
        "CURLE_RECURSIVE_API_CALL",
        "Uma função API foi chamada de dentro de uma ligação de "
        . "retorno (callback)."
      ],
    94 => [
        "CURLE_AUTH_ERROR",
        "Uma função de autenticação retornou um erro."
      ],
    95 => [
        "CURLE_HTTP3",
        "Foi detectado um problema na camada HTTP/3. Isto é um tanto "
        . "genérico e pode ser um entre vários problemas, consulte o "
        . "buffer de erros para obter detalhes."
      ],
    96 => [
        "CURLE_QUIC_CONNECT_ERROR",
        "Erro de conexão QUIC. Este erro pode ser causado por um "
        . "erro de biblioteca SSL. QUIC é o protocolo utilizado para "
        . "transferências HTTP/3."
      ]
  ];

  /**
   * A mensagem de exceção.
   * 
   * @var string
   */
  protected $errorPhrase;

  /**
   * O código de erro definido para esta exceção
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
   * @param int $errorCode
   *   O código do erro cURL
   * @param string $errorPhrase
   *   A mensagem de erro do cURL (opcional, se nula utilizará a
   * mensagem padrão)
   */
  public function __construct(string $url, string $method,
    int $errorCode, $errorPhrase = null)
  {
    if (null === $errorPhrase && isset($this->errorCodes[$errorCode])) {
      $this->errorPhrase = 'Erro '
        . $this->errorCodes[$errorCode][0] . ': '
        . $this->interpolate($this->errorCodes[$errorCode][1],
          [ 'url' => $url, 'method' => $method ]
      );
    } else {
      $this->errorPhrase = $errorPhrase;
    }

    // Armazena o código de erro
    $this->code = $errorCode;

    parent::__construct($this->errorPhrase, $errorCode, null);
  }

  /**
   * Converte a exceção para string.
   * 
   * @return string
   *   A mensagem de exceção
   */
  public function __toString()
  {
    return sprintf('Erro cURL [%d]: %s', $this->code, $this->errorPhrase);
  }
}