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
 * Tradicionalmente, uma página da web deve enviar uma solicitação ao
 * servidor para receber novos dados, ou seja, a página solicita dados
 * do servidor. Com eventos enviados pelo servidor, é possível que um
 * servidor envie novos dados para uma página da web a qualquer momento,
 * enviando mensagens para a página da web.
 * 
 * Essas mensagens recebidas podem ser tratadas como eventos + dados
 * dentro da página da web.
 *
 * O Server-sent events - SSE basicamente cria um soquete como uma
 * solicitação sobre HTTP, no entanto, a conexão não fecha a menos que
 * você peça para ser fechada. Se o navegador ouvir que fecha, ele
 * reabre automaticamente a conexão para começar a escutar novamente.
 * 
 * Eles são uma alternativa HTML5 para web sockets, pesquisas longas e
 * pesquisas curtas, alguns podem dizer que ainda estão pesquisando, mas
 * com a adição de ter o navegador lidando com eles em vez do
 * desenvolvedor.
 * 
 * Esta classe implementa o SSE do lado do servidor, fornecendo um
 * wrapper em torno das operações mais comuns, incluindo a serialização
 * de todo o fluxo em uma string. Envolve um retorno de chamada e
 * invoca-o para transmiti-lo. Somente uma invocação é permitida.
 * Múltiplas invocações retornarão uma seqüência vazia para a segunda e
 * demais chamadas subseqüentes.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types=1);

namespace Core\Streams;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;
use function array_key_exists;
use const SEEK_SET;

class ServerSentEventHandler
  implements StreamInterface
{
  /**
   * O callback.
   * 
   * @var callable|null
   */
  protected $callback = null;

  public function __construct(callable $callback)
  {
    $this->attach($callback);
  }

  /**
   * Lê todos os dados do fluxo em uma string, do começo ao fim.
   *
   * Este método DEVE tentar buscar o início do fluxo antes de ler os
   * dados e ler o fluxo até que o fim seja alcançado.
   * 
   * Aviso: isso pode tentar carregar uma grande quantidade de dados na
   * memória.
   *
   * Este método NÃO DEVE gerar uma exceção para estar em conformidade
   * com as operações de conversão de strings do PHP.
   * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
   * 
   * @return string
   */
  public function __toString()
  {
    return $this->getContents();
  }
  
  /**
   * Fecha o fluxo e todos os recursos subjacentes.
   */
  public function close()
  {
    $this->callback = null;
  }
  
  /**
   * Anexa um novo callback à instância
   */
  public function attach(callable $callback):void
  {
    $this->callback = $callback;
  }
  
  /**
   * Separa quaisquer recursos subjacentes do fluxo. Depois que o fluxo
   * foi desanexado, ele fica em um estado inutilizável.
   * 
   * @return resource|null
   *   Retorna o fluxo PHP subjacente, se houver
   */
  public function detach()
  {
    $callback = $this->callback;
    $this->callback = null;
    
    return $callback;
  }

  /**
   * Obtém o tamanho do fluxo, se conhecido.
   * 
   * @return int|null
   *   Retorna o tamanho do fluxo em bytes, se conhecido, ou nulo se
   *   desconhecido.
   */
  public function getSize()
  {
    return null;
  }
  
  /**
   * Retorna a posição atual do ponteiro de leitura/gravação do fluxo.
   * 
   * @return int
   *   A posição atual do ponteiro
   * 
   * @throws RuntimeException
   *   Em caso de erro
   */
  public function tell()
  {
    throw new RuntimeException("Fluxos SSE não podem dizer sua "
      . "posição."
    );
  }
  
  /**
   * Retorna verdadeiro se o fluxo estiver no final do fluxo.
   * 
   * @return bool
   */
  public function eof()
  {
    return empty($this->callback);
  }
  
  /**
   * Retorna se o fluxo é ou não pesquisável.
   * 
   * @return boolean
   */
  public function isSeekable()
  {
    // Sempre retorna falso pois este tipo de serviço não pode ser
    // pesquisável
    return false;
  }

  /**
   * Procura uma determinada posição no fluxo.
   * 
   * @link http://www.php.net/manual/en/function.fseek.php
   * @param int $offset
   *   O deslocamento dentro do fluxo
   * @param int $whence
   *   Especifica como a posição do cursor será calculada com base no
   *   deslocamento de busca. Os valores válidos são idênticos aos
   *   valores internos de PHP $whence para `fseek ()`:
   *     SEEK_SET: Define a posição igual aos bytes de deslocamento
   *     SEEK_CUR: Define a posição para a localização atual mais
   *               deslocamento
   *     SEEK_END: Define a posição para o fim do fluxo mais
   *               deslocamento
   * 
   * @return bool
   *   Retorna verdadeiro em caso de sucesso ou falso em caso de falha
   * 
   * @throws RuntimeException
   *   Em caso de falha
   */
  public function seek($offset, $whence = SEEK_SET)
  {
    throw new RuntimeException("Fluxos SSE não podem buscar uma "
      . "posição específica."
    );
  }

  /**
   * Procura o início do fluxo. Se o fluxo não for pesquisável, este
   * método levantará uma exceção; caso contrário, ele executará
   * seek(0).
   * 
   * @see seek()
   * @link http://www.php.net/manual/en/function.fseek.php
   * 
   * @throws RuntimeException on failure.
   */
  public function rewind()
  {
    if (!$this->isSeekable()) {
      throw new RuntimeException("Não é possível retroceder o fluxo.");
    }
    
    $this->seek(0);
  }
  
  /**
   * Retorna se nossa stream permite a escrita.
   * 
   * @return boolean
   */
  public function isWritable()
  {
    // Sempre retorna falso pois neste tipo de fluxo não podemos
    // escrever
    return false;
  }
  
  /**
   * Escreve dados em nossa stream.
   * 
   * @param string $string
   *   A string a ser escrita
   * 
   * @return int
   *   Retorna o número de bytes gravados no fluxo
   * 
   * @throws RuntimeException
   *   Em caso de falha
   */
  public function write($string)
  {
    // Sempre retorna uma exceção pois nosso fluxo não permite a
    // gravação
    throw new RuntimeException("Não é possível gravar em fluxos SSE.");
  }
  
  /**
   * Retorna se nosso fluxo pode ser lido.
   * 
   * @return boolean
   */
  public function isReadable()
  {
    return true;
  }
  
  /**
   * Lê dados de nosso fluxo.
   * 
   * @param int $length
   *   Leia até $length bytes do objeto e os retorne. Menos de $length
   *   bytes podem ser retornados se a chamada de fluxo subjacente
   *   retornar menos bytes
   * 
   * @return string
   *   Retorna os dados lidos do fluxo ou uma string vazia se nenhum
   *   byte estiver disponível
   * 
   * @throws RuntimeException
   *   Se não for possível ler ou ocorrer um erro durante a leitura
   */
  public function read($length)
  {
    return $this->getContents();
  }

  /**
   * Retorna o conteúdo restante em uma string. Executa o retorno de
   * chamada com buffer de saída.
   * 
   * @return string
   *   O conteúdo de nossa stream
   * 
   * @throws RuntimeException
   *   Se não for possível ler ou ocorrer um erro durante a leitura
   */
  public function getContents()
  {
    // Separa quaisquer recursos subjacentes do fluxo e obtém o fluxo
    $callback = $this->detach();

    try
    {
      $contents = $callback ? $callback() : '';
    }
    catch (Throwable $error)
    {
      // Converte quaisquer erros em RuntimeException
      throw new RuntimeException("Erro ao ler o fluxo: " .
        $error->getMessage(),
        $error->getCode(),
        $error
      );
    }

    return (string) $contents;
  }

  /**
   * Obtém os metadados do fluxo como uma matriz associativa ou recupera
   * uma chave específica dos metadados
   * 
   * As chaves retornadas são idênticas às chaves retornadas da função
   * stream_get_meta_data() do PHP.
   * 
   * @link http://php.net/manual/en/function.stream-get-meta-data.php
   * @param string $key
   *   O metadado específico a ser recuperado
   * 
   * @return array|mixed|null
   *   Retorna uma matriz associativa se nenhuma chave for fornecida.
   *   Retorna um valor de uma chave específica se uma chave for
   *   fornecida e o valor for encontrado ou nulo se a chave não for
   *   encontrada.
   */
  public function getMetadata($key = null)
  {
    $metadata = [
      'eof' => $this->eof(),
      'stream_type' => 'callback',
      'seekable' => false
    ];

    if (null === $key) {
      return $metadata;
    }
    
    if (!array_key_exists($key, $metadata)) {
      return null;
    }

    return $metadata[$key];
  }
}
