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
 * Essa é uma classe que permite manipular diretórios e arquivos. No
 * caso de diretórios, permite iterar sobre o mesmo, obtendo o seu
 * conteúdo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

use DateTime;
use Countable;
use IteratorAggregate;
use Exception;
use Traversable;

class Path
  implements IteratorAggregate, Countable
{
  /**
   * O caminho.
   * 
   * @var string
   */
  protected $path;
  
  /**
   * O construtor da classe.
   * 
   * @param string $path
   *   O caminho para um diretório ou arquivo
   */
  public function __construct(string $path)
  {
    $this->path = $path;
  }
  
  /**
   * Obtém se o caminho informado existe.
   * 
   * @return bool
   */
  public function exists(): bool
  {
    return $this->isDirectory() 
      ? true 
      : stream_resolve_include_path( $this->path )
    ;
  }
  
  /**
   * Obtém se o caminho pode ser gravável.
   * 
   * @return boolean
   */
  public function isWritable(): bool
  {
    return is_writable( $this->path );
  }
  
  /**
   * Obtém se o caminho pode ser lido.
   * 
   * @return boolean
   */
  public function isReadable(): bool
  {
    return is_readable( $this->path );
  }
  
  /**
   * Obtém se o arquivo que está informado no caminho é executável e/ou
   * se o caminho pode ter conteúdos executáveis.
   * 
   * @return boolean
   */
  public function isExecutable(): bool
  {
    return is_executable( $this->path );
  }
  
  /**
   * Obtém se o arquivo que está informado no caminho é um arquivo
   * comum.
   * 
   * @return boolean
   */
  public function isFile(): bool
  {
    return is_file( $this->path );
  }
  
  /**
   * Obtém se o caminho informado é um diretório.
   * 
   * @return boolean
   */
  public function isDirectory(): bool
  {
    return is_dir( $this->path );
  }
  
  /**
   * Obtém se o caminho informado é um link simbólico (symbolic link).
   * 
   * @return boolean
   */
  public function isLink(): bool
  {
    return is_link( $this->path );
  }
  
  /**
   * Obtém a extensão do arquivo informado no caminho.
   * Ex.: Para '/www/htdocs/index.html' ontém 'html'.
   * 
   * @return string
   *   A extensão do arquivo
   * 
   * @return string
   */
  public function getExtension(): string
  {
    return $this->isFile() 
      ? pathinfo( $this->path, PATHINFO_EXTENSION )
      : ''
    ;
  }
  
  /**
   * Obtém o nome do arquivo informado no caminho.
   * Ex.: Para '/www/htdocs/index.html' ontém 'index'.
   * 
   * @return string
   */
  public function getFilename(): string
  {
    return $this->isFile() 
      ? pathinfo( $this->path, PATHINFO_FILENAME )
      : ''
    ;
  }
  
  /**
   * Obtém o diretório do caminho informado.
   * Ex.: Para '/www/htdocs/index.html' ontém '/www/htdocs'.
   * 
   * @return string
   */
  public function getDirectory(): string
  {
    return $this->isFile() 
      ? pathinfo( $this->path, PATHINFO_DIRNAME )
      : $this->path
    ;
  }
  
  /**
   * Obtém o nome base (nome e extensão) do arquivo informado no
   * caminho. Ex.: Para '/www/htdocs/index.html' ontém 'index.html'.
   * 
   * @return string
   */
  public function getBasename(): string
  {
    return $this->isFile() 
      ? pathinfo( $this->path, PATHINFO_BASENAME )
      : ''
    ;
  }
  
  /**
   * Lê o grupo do arquivo informado no caminho. O ID do grupo é
   * retornado no formato numérico.
   * 
   * @return int
   *   Retorna o group ID do arquivo, ou FALSE no caso de um erro
   */
  public function getAccessGroup(): int
  {
    return filegroup( $this->path );
  }
  
  /**
   * Lê o dono (owner) do arquivo informado no caminho. O ID do usuário
   * é retornado no formato numérico.
   * 
   * @return int
   *   Retorna o user ID do arquivo, ou FALSE no caso de um erro
   */
  public function getAccessOwner(): int
  {
    return fileowner( $this->path );
  }
  
  /**
   * Obtém o tamanho do arquivo informado no caminho.
   * 
   * @return int
   *   O tamanho do arquivo
   */
  public function getSize(): int
  {
    return filesize( $this->path );
  }
  
  /**
   * Obtém o tempo de modificação do arquivo informado no caminho.
   * 
   * @return DateTime
   *   O tempo de modificação do arquivo
   */
  public function getModificationTime(): DateTime
  {
    return new DateTime( filemtime( $this->path ) );
  }
  
  /**
   * Obtém o tempo de criação do arquivo informado no caminho.
   * 
   * @return DateTime
   *   O tempo de criação do arquivo
   */
  public function getCreationTime(): DateTime
  {
    return new DateTime( filectime( $this->path ) );
  }
  
  /**
   * Obtém o último horário de acesso do arquivo informado no caminho.
   * 
   * @return DateTime
   *   O último horário de acesso do arquivo
   */
  public function getAccessTime(): DateTime
  {
    return new DateTime( fileatime( $this->path ) );
  }
  
  /**
   * Obtém o path absoluto canonicalizado do caminho/arquivo informado.
   * Expande todos os links simbólicos e resolve referências para
   * '/./', '/../' e extra caracteres '/' na entrada pelo caminho.
   * 
   * @return string
   */
  public function getRealPath(): string
  {
    return realpath( $this->path );
  }

  /**
   * Força a criação do caminho.
   * 
   * @return bool
   */
  public function createDirectory(): bool
  {
    return $this->createPath( $this->path );
  }

  /** 
   * Cria os diretórios ao longo de um caminho recursivamente.
   * 
   * @param string $path
   *   O caminho a ser criado
   * 
   * @return bool
   *   Se o caminho pode ser criado
   */
  protected function createPath(string $path): bool
  {
    if (is_dir($path)) {
      return true;
    } else {
      $previousPath = substr($path, 0, strrpos($path, '/', -2) + 1 );

      if ($this->createPath($previousPath)) {
        if (is_writable($previousPath)) {
          // Cria o subdiretório com as mesmas permissões do caminho pai
          try {
            $oldMask = umask(0);          
            $result = mkdir($path, fileperms($previousPath));
          } finally {
            umask($oldMask);
          }

          return $result;
        }
      }

      return false;
    }
  }
  
  /**
   * Converte o caminho para uma string.
   * 
   * @return string
   *   O caminho informado
   */
  public function __toString()
  {
    return $this->path;
  }
  
  /**
   * Obtém os itens contidos no diretório informado no caminho de uma
   * forma que possamos iterar sobre os mesmos.
   * 
   * @throws Exception
   *   Em caso de erro
   * 
   * @return Traversable
   */
  public function getFiles(): Traversable
  {
    if ($this->isDirectory()) {
      // Abrimos o diretório
      $dir = opendir($this->path);
      
      // Percorre os itens do diretório
      while ( $item = readdir($dir) ) {
        // Ignora os itens especiais
        //   '.': diretório corrente
        //  '..': diretório superior
        if ($item === '.' || $item === '..') {
          continue;
        }

        // Devolve o item contido no diretório acrescido do caminho do
        // próprio diretório
        yield new Path($this->concat($this->path, $item));
      }
      
      closedir($dir);
    } else {
      // Dispara uma exceção pois o caminho informado não é um
      // diretório
      throw new Exception("O caminho `{$this->path}` não é um "
        . "diretório e, portanto, não possui arquivos.");
    }
  }
  
  /**
   * Obtém um iterador sobre o conteúdo do diretório informado no
   * caminho.
   * 
   * @throws Exception
   *   Em caso de erro
   * 
   * @return Traversable
   */
  public function getIterator(): Traversable
  {
    return $this->getFiles();
  }
  
  /**
   * Obtém o tamanho do diretório.
   * 
   * @return int
   *   O número de itens armazenados no diretório
   */
  public function count(): int
  {
    return iterator_count($this->getIterator());
  }
  
  /**
   * Combina os conteúdos de dois caminhos.
   * 
   * @param string $folder
   *   O caminho inicial
   * @param string $path
   *   O caminho contido no caminho inicial
   * 
   * @return string 
   */
  private function concat(
    string $folder,
    string $path
  ): string
  {
    // Verifica se temos barras à direita
    if ($folder[ strlen( $folder ) - 1 ] !== DIRECTORY_SEPARATOR) {
      // Acrescentamos a barra à direita
      $folder .= DIRECTORY_SEPARATOR;
    }
    
    // Verifica se temos barras à esquerda    
    if ($path[ 0 ] === DIRECTORY_SEPARATOR) {
      // Removemos barras à esquerda
      $path = substr( $path, 1 );
    }
    
    return $folder . $path;
  }
}