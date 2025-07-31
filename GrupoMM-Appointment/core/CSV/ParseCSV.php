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
 * Uma classe para permitir a leitura de um arquivo CSV.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\CSV;

use Countable;
use Iterator;
use InvalidArgumentException;
use RuntimeException;
use function each;

class ParseCSV
 implements Countable, Iterator
{
  /**
   * O conteúdo do arquivo.
   *
   * @var array
   */
  protected array $content = [];

  /**
   * A posição no conteúdo.
   *
   * @var integer
   */
  protected int $position = 0;

  /**
   * O construtor.
   *
   * @param string $filename
   *   O nome do arquivo CSV
   * @param string $seperator (opcional)
   *   O caracter separador de campos
   * @param string $delimiter (opcional)
   *   O caracter delimitador do campo
   */
  function __construct(
    string $filename,
    bool $parseHeader=true,
    string $seperator=',',
    string $delimiter = '"'
  )
  {
    // Verifica se o arquivo existe
    if ( !file_exists($filename) ) {
      throw new InvalidArgumentException("O arquivo '{$filename}' não "
        . "existe."
      );
    }

    // Verifica se conseguimos ler o arquivo
    if ( !is_readable($filename) ) {
      throw new RuntimeException("O arquivo '{$filename}' não "
        . "pode ser lido."
      );
    }

    // Carregamos todo o conteúdo do arquivo e interpretamos com um CSV
    $csv = array_map(
      function($line) use ($seperator, $delimiter) {
        return str_getcsv($line, $seperator, $delimiter);
      },
      file($filename, FILE_IGNORE_NEW_LINES)
    );

    if ($parseHeader) {
      // Utilizamos a primeira linha do arquivo como sendo o cabeçalho com
      // os nomes das colunas
      array_walk($csv, function(&$a) use ($csv) {
        $a = array_combine($csv[0], $a);
      });

      // Então, removemos a primeira linha, já que a mesma contém apenas
      // os nomes dos campos
      array_shift($csv);
    }

    $this->content = $csv;
  }
  
  // ================================[ Implementação da Countable ]=====

  /**
   * Conta os elementos armazenados no arquivo CSV.
   * 
   * @return int
   *   A quantidade de elementos armazenados
   */
  public function count()
  {
    return count($this->content);
  }


  // =================================[ Implementação da Iterator ]=====
  
  /**
   * Retorna ao início
   *
   * @return void
   */
  function rewind(): void
  {
    $this->position = 0;
  }

  /**
   * Obtém o valor da posição corrente
   *
   * @return mixed
   */
  function current()
  {
    return $this->content[ $this->position ];
  }

  /**
   * Obtém a posição corrente.
   *
   * @return mixed
   */
  function key() {
    return $this->position;
  }

  /**
   * Avança para o próximo registro.
   *
   * @return void
   */
  function next(): void {
    ++$this->position;
  }

  /**
   * Determina se a posição atual é válida.
   *
   * @return bool
   */
  function valid(): bool {
    return isset($this->content[$this->position]);
  }
}