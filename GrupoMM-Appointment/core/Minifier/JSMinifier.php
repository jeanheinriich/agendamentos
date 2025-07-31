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
 * Classe responsável por reduzir um conteúdo que contém um código
 * JavaScript, removendo caracteres desnecessários e reduzir o tamanho
 * do conteúdo a ser enviado ao navegador, sem alterar sua
 * funcionalidade.
 */

namespace Core\Minifier;

use Exception;
use RuntimeException;

class JSMinifier
  extends AbstractMinifier
{
  /**
   * O código JavaScript a ser minificado
   *
   * @var string
   */
  protected $input;

  /**
   * A localização do caractere (na sequência de entrada) que será o
   * próximo a ser processado
   *
   * @var int
   */
  protected $index = 0;

  /**
   * O primeiro dos caracteres atualmente sendo examinado
   *
   * @var string
   */
  protected $a = '';

  /**
   * O próximo caractere sendo examinado (após a)
   *
   * @var string
   */
  protected $b = '';

  /**
   * Esse caractere somente será usando quando determinadas ações de
   * antecipação ocorrerem
   *
   *  @var string
   */
  protected $c;

  /**
   * O tamanho do código JavaScript
   *
   * @var int
   */
  protected $len = 0;

  /**
   * Contém IDs de bloqueio usados para substituir certos padrões de
   * código e impedir que eles sejam minificados
   *
   * @var array
   */
  protected $locks = [];

  /**
   * Estes caracteres são usados para delimitar uma string
   */
  protected $stringDelimiters = [
    '\'' => true,
    '"' => true,
    '`' => true
  ];

  /**
   * Caracteres que não podem ficar sozinhos e precisam preservar a nova
   * linha
   *
   * @var array
   */
  protected $noNewLineCharacters = [
    '(' => true,
    '-' => true,
    '+' => true,
    '[' => true,
    '@' => true
  ];

  /**
   * Analisa um conteúdo fornecido que contém um código Javascript,
   * removendo os caracteres desnecessários para reduzir o conteúdo sem
   * alterar sua funcionalidade.
   *
   * @param string $source
   *   O conteúdo a ser reduzido
   *
   * @return string
   *   O conteúdo reduzido (minificado)
   * 
   * @throws Exception
   *   Em caso de erro na redução
   */
  public function minify(string $source): string
  {
    try {
      ob_start();

      // Inicialmente bloqueia conteúdos que não podem ser modificados
      $content = $this->lock($source);

      // Normaliza o conteúdo, eliminando caracteres de retorno de carro
      // e armazena o conteúdo para processamento
      $this->input = $this->normalize($content);
      unset($content);

      // Salva o comprimento da entrada para pular o cálculo toda vez
      $this->len = strlen($this->input);
      
      // Adicionamos uma nova linha ao final do código para facilitar o
      // tratamento dos comentários na parte inferior do código. Isso
      // evita o erro de comentário não fechado que pode ocorrer de outra
      // forma
      $this->input .= PHP_EOL;

      // Inicializamos as variáveis
      
      // Preencha "a" com uma nova linha e "b" com o primeiro caractere
      // antes de iniciar o analisador
      $this->a = "\n";
      $this->b = $this->getReal();

      // Processa o código fonte JavaScript, recuperando apenas os
      // códigos propriamente ditos, eliminando todos os caracteres
      // desnecessários
      $this->parse();

      // Limpa as variáveis internas
      $this->clean();

      // Às vezes, há uma nova linha líder, por isso cortamos isso aqui
      $minified = ltrim(ob_get_clean());
      $minified = $this->unlock($minified);

      return $minified;
    }
    catch(Exception $e) {
      // Como a função provavelmente não foi concluída, nós a limpamos
      // antes de descartá-la.
      $this->clean();

      // Sem essa chamada, as coisas ficam estranhas, com códigos
      // parcialmente gerados
      ob_end_clean();

      throw $e;
    }
  }

  /**
   * Normaliza o conteúdo, eliminando novas linhas.
   *
   * @param string $source
   *   O código JavaScript a ser minificado
   */
  protected function normalize($source)
  {
    $this->debug("Inicializando código");

    // Normaliza o código, primeiramente
    $this->debug("Removendo o caractere de retorno de carro seguido de "
      . "nova linha"
    );
    $source = str_replace("\r\n", "\n", $source);
    $this->debug("Removendo comentários vazios");
    $source = str_replace('/**/', '', $source);
    $this->debug("Substituíndo o caractere retorno de carro por nova linha");
    $source = str_replace("\r", "\n", $source);

    return $source;
  }

  /**
   * Substitua os padrões na sequência especificada e armazene a
   * substituição.
   *
   * @param string $source
   *   O conteúdo a ser bloqueado
   * 
   * @return string
   */
  protected function lock($source): string
  {
    $this->debug('Bloqueando código que não pode ser modificado');

    // Bloqueia coisas como <code>"asd" + ++x;</code>
    $lock = '"LOCK---' . crc32(time()) . '"';

    $matches = array();
    preg_match('/([+-])(\s+)([+-])/S', $source, $matches);
    if (empty($matches)) {
      return $source;
    }

    $this->locks[$lock] = $matches[2];

    $source = preg_replace('/([+-])\s+([+-])/S', "$1{$lock}$2", $source);

    return $source;
  }

  /**
   * Substitua "bloqueios" pelos caracteres originais.
   *
   * @param string $source
   *   O código para desbloquear
   * 
   * @return string
   */
  protected function unlock($source): string
  {
    $this->debug('Desbloqueando código que não pode ser modificado');
    if (empty($this->locks)) {
      return $source;
    }

    foreach ($this->locks as $lock => $replacement) {
      $source = str_replace($lock, $replacement, $source);
    }

    return $source;
  }

  /**
   * Essa função percorre o código javascript fornecido, copiando todo o
   * código relevante e descartando qualquer coisa que não seja
   * necessária, como comentários e espaços desnecessários.
   */
  protected function parse(): void
  {
    $this->debug('Iniciando loop de análise');

    while (    $this->a !== false
            && !is_null($this->a)
            && $this->a !== '') {
      switch ($this->a) {
        // Novas linhas
        case "\n":
          // Se o próximo caractere indicar que o conteúdo não permite a
          // supressão de nova linha, então preservamos
          if ( $this->b !== false
               && isset($this->noNewLineCharacters[$this->b]) ) {
            print $this->a;
            $this->saveString();

            break;
          }

          // Se "b" é um espaço, pulamos o resto do bloco de comutação e
          // descemos para a verificação de string/regex abaixo,
          // redefinindo $this->b com getReal
          if ($this->b === ' ') {
            break;
          }
        case ' ':
          // Caso contrário, tratamos a nova linha como um espaço
          if($this->isAlphaNumeric($this->b)) {
            print $this->a;
          }

          $this->saveString();

          break;
        default:
          // Analisamos o próximo caractere
          switch ($this->b) {
            case "\n":
              if ($this->contains('}])+-"\'', $this->a)) {
                print $this->a;
                $this->saveString();

                break;
              } else {
                if ($this->isAlphaNumeric($this->a)) {
                  print $this->a;
                  $this->saveString();
                }
              }

              break;
            case ' ':
              if (!$this->isAlphaNumeric($this->a)) {
                break;
              }

              // Não para
            default:
              // Verifique se há alguma expressão regular que quebra o
              // código
              if ( $this->a === '/'
                   && ($this->b === '\'' || $this->b === '"') ) {
                $this->saveRegex();

                continue 3;
              }

              print $this->a;
              $this->saveString();

              break;
          }

          break;
      }

      // Recupera o próximo caractere real, ignorando os comentários
      $this->b = $this->getReal();

      // Se 'b' for uma expressão regular, armazenamos
      if ($this->b == '/' && $this->contains('(,=:[!&|?', $this->a)) {
        $this->saveRegex();
      }
    }
  }

  /**
   * Redefine os atributos que não precisam ser armazenados entre
   * solicitações, para que a próxima solicitação esteja pronta. Outro
   * motivo para isso é garantir que as variáveis sejam limpas e não
   * ocupem memória.
   */
  protected function clean(): void
  {
    unset($this->input);
    $this->len   = 0;
    $this->index = 0;
    $this->a     = $this->b = '';
    unset($this->c);
    unset($this->options);
  }

  /**
   * Retorna a próxima string para processamento com base no índice
   * atual.
   *
   * @return string
   */
  protected function getChar(): string
  {
    // Verifique se há algo no buffer antecipado e use-o
    if (isset($this->c)) {
      $char = $this->c;
      unset($this->c);
    } else {
      // Caso contrário, começamos a puxar da entrada
      $char = ($this->index < $this->len)
        ? $this->input[$this->index]
        : false
      ;

      // Se o próximo caractere não existir, retorne falso
      if (isset($char) && $char === false) {
        return false;
      }

      // Caso contrário, aumente o ponteiro e use esse caractere
      $this->index++;
    }

    // Normalize todo o espaço em branco, exceto o caractere de nova
    // linha, em um espaço padrão.
    if ($char !== "\n" && $char < "\x20") {
      return ' ';
    }

    return $char;
  }

  /**
   * Esta função obtém o próximo caractere "real". É essencialmente um
   * invólucro em torno da função getChar que ignora os comentários.
   * Isso tem benefícios significativos de desempenho, já que o salto é
   * feito usando funções nativas (ou seja, código c), e não no script
   * php.
   *
   * @return string
   *   O próximo caractere 'real' para processar
   * 
   * @throws RuntimeException
   */
  protected function getReal(): string
  {
    $startIndex = $this->index;
    $char = $this->getChar();

    // Verifique se estamos potencialmente em um comentário
    if ($char !== '/') {
      return $char;
    }

    $this->c = $this->getChar();

    if ($this->c === '/') {
      $this->processOneLineComments($startIndex);

      return $this->getReal();
    } elseif ($this->c === '*') {
      $this->processMultiLineComments($startIndex);

      return $this->getReal();
    }

    return $char;
  }

  /**
   * Removidos os comentários de uma linha, com exceção de alguns tipos
   * muito específicos de comentários condicionais.
   *
   * @param int $startIndex
   *   O ponto do índice em que a função "getReal" foi iniciada
   */
  protected function processOneLineComments($startIndex): void
  {
    $thirdCommentString = ($this->index < $this->len)
      ? $this->input[$this->index]
      : false
    ;

    // Matamos o resto da linha
    $this->getNext("\n");

    unset($this->c);

    if ($thirdCommentString == '@') {
      $endPoint = $this->index - $startIndex;
      $this->c = "\n" . substr($this->input, $startIndex, $endPoint);
    }
  }

  /**
   * Ignora os comentários de várias linhas, quando apropriado, e
   * inclui-os quando necessário. Comentários condicionais e blocos de
   * estilo "licença" são preservados.
   *
   * @param  int $startIndex
   *   O ponto do índice em que a função "getReal" foi iniciada
   * 
   * @throws RuntimeException
   *   Comentários não fechados geram um erro
   */
  protected function processMultiLineComments($startIndex): void
  {
    $this->getChar(); // Corrente C
    $thirdCommentString = $this->getChar();

    // Elimine tudo até que o próximo '*/'' esteja lá
    if ($this->getNext('*/')) {
      $this->getChar(); // Obtém '*'
      $this->getChar(); // Obtém '/'
      $char = $this->getChar(); // Obtém o próximo caractere real
 
      // Now we reinsert conditional comments and YUI-style licensing comments
      if ( ( $this->options['flaggedComments']
             && $thirdCommentString === '!')
           || ($thirdCommentString === '@') ) {
        // Se comentários condicionais ou comentários sinalizados não
        // são a primeira coisa no script, precisamos ecoar e
        // preenchê-lo com um espaço antes de prosseguir
        if ($startIndex > 0) {
          print $this->a;
          $this->a = " ";

          // Se o comentário começou em uma nova linha, deixamos que
          // ele permaneça na nova linha
          if ($this->input[($startIndex - 1)] === "\n") {
            print "\n";
          }
        }

        $endPoint = ($this->index - 1) - $startIndex;
        print substr($this->input, $startIndex, $endPoint);

        $this->c = $char;

        return;
      }
    } else {
      $char = false;
    }

    if ($char === false) {
      throw new RuntimeException("Comentário multilinha não fechado na "
        . "posição: " . ($this->index - 2)
      );
    }

    // Se estamos aqui 'c' faz parte do comentário e, portanto, usamos
    $this->c = $char;
  }

  /**
   * Avança o índice para a próxima instância da string fornecida. Se
   * for encontrado, o primeiro caractere da string é retornado e o
   * índice é definido para sua posição.
   *
   * @param string $element
   *   O elemento que estamos tentando localizar
   * 
   * @return string|false
   *   Retorna o primeiro caractere da string ou falso
   */
  protected function getNext(string $element)
  {
    // Encontre a próxima ocorrência de "element" após a posição atual
    $pos = strpos($this->input, $element, $this->index);

    // Se não estiver lá, retorne false
    if ($pos === false) {
      return false;
    }

    // Ajuste a posição do índice para avançar na sequência solicitada
    $this->index = $pos;

    // Retorne o primeiro caractere dessa sequência
    return $this->index < $this->len
      ? $this->input[$this->index]
      : false
    ;
  }

  /**
   * Quando uma string JavaScript é detectada, essa função rastreia até
   * o final e salva a string inteira.
   *
   * @throws RuntimeException
   *   Strings não fechadas geram um erro
   */
  protected function saveString()
  {
    $startPos = $this->index;

    // Esta função é sempre chamada depois que 'a' é limpa, então
    // empurramos 'b' para esse local
    $this->a = $this->b;

    // Se o caractere inicial não indicar que é uma string, simplesmente
    // retorna
    if (!isset($this->stringDelimiters[$this->a])) {
      return;
    }

    // Tipos strings são delimitados por " ou '
    $delimiter = $this->a;

    // Ecoar este delimitador inicial
    $this->debug("Analisando string delimitada por " . $this->a);
    $stringType = $this->a;
    print $this->a;

    // Continua até que a string seja finalizada
    while (true) {
      // Pegue o próximo caractere e carregue-o em 'a'
      $this->a = $this->getChar();

      switch ($this->a) {
        // Se o delimitador de string (aspas simples ou duplas) for
        // usado, descarregamos ele e terminamos o loop, pois o string
        // foi concluído
        case $delimiter:
          break 2;

        // Novas linhas em strings sem delimitadores de linha são ruins.
        // As novas linhas reais serão representadas pela string \n e
        // não pelo caractere real; portanto, elas serão tratadas
        // perfeitamente usando o bloco de chave abaixo
        case "\n":
          if ($stringType === '`') {
            print $this->a;
          } else {
            throw new RuntimeException("String não fechado na posição: "
              . $startPos
            );
          }

          break;
        case '\\':
          // Caracteres de escape são identificados aqui. Se é uma nova
          // linha que escapou, não é realmente necessário.
          
          // 'a' é uma barra. Queremos mantê-lo, e o próximo caractere,
          // a menos que seja uma nova linha. Novas linhas como cadeias
          // de caracteres reais serão preservadas, mas novas linhas de
          // escape devem ser reduzidas
          $this->b = $this->getChar();

          // Se 'b' é uma nova linha, descartamos 'a' e 'b' e
          // reiniciamos o loop
          if ($this->b === "\n") {
            break;
          }

          // Ecoamos o caractere de escape e reiniciamos o loop
          print $this->a . $this->b;

          break;
        default:
          // Como não estamos lidando com nenhum caso especial,
          // simplesmente reproduzimos o caractere e continuamos nosso
          // loop
          print $this->a;
      }
    }

    $this->debug("Terminada análise da string. Retornado conteúdo: "
      . $this->a
    );
  }

  /**
   * Quando uma expressão regular é detectada, essa função rastreia o
   * final dela e salva a expressão regular inteira.
   *
   * @throws RuntimeException
   *   Em caso de uma expressão regular não fechada
   */
  protected function saveRegex()
  {
    print $this->a . $this->b;

    while (($this->a = $this->getChar()) !== false) {
      if($this->a === '/') {
        break;
      }

      if ($this->a === '\\') {
        print $this->a;
        $this->a = $this->getChar();
      }

      if($this->a === "\n") {
        throw new RuntimeException("Expressão regular não fechada na "
          . "posição: " . $this->index
        );
      }

      print $this->a;
    }

    $this->b = $this->getReal();
  }

  /**
   * Verifica se um caractere é alfanumérico.
   *
   * @param string $char
   *   Apenas um caractere
   * 
   * @return bool
   */
  protected function isAlphaNumeric(string $char): bool
  {
    return preg_match('/^[\w\$\pL]$/', $char) === 1 || $char == '/';
  }
}
