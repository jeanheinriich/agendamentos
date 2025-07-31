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
 * Classe responsável pela coleta de lixos em um diretório. Ela faz o
 * rastreamento de um diretório, procurando por arquivos modificados a
 * mais de 'n' dias, destruindo-os.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

class GarbageCollector
{
  /**
   * Remove arquivos obsoletos de um diretório. São considerados
   * obsoletos arquivos que não tenham sido modificados a mais de $days
   * dias.
   *
   * @param string $directory
   *   O caminho para o diretório que iremos analisar
   * @param integer $days
   *   A quantidade de dias a partir do qual um arquivo deve ser
   *   considerado obsoleto e precisa ser excluído
   *
   * @return bool
   */
  public static function dropOldFiles(string $directory,
    int $days = 30): bool
  {
    $allDropped = true;
    $now = time();
    
    if (is_dir($directory)) {
      $directoryHandle = opendir($directory);
      
      // Verifica se temos um diretório válido
      if (!$directoryHandle) {
        trigger_error("Não foi possível abrir o diretório $directory",
          E_USER_WARNING
        );
        
        return false;
      }
      
      while ($file = readdir($directoryHandle)) {
        // Pula os arquivos especiais e partes do sistema
        if ($file == '.' || $file == '..' || $file == '.keepme'
            || $file == 'README.md') {
          continue;
        }
        
        // Recupera o nome do arquivo e a data do mesmo
        $fullName = $directory.'/'.$file;
        $old = $now - filemtime($fullName);
        
        // Verifica se é um diretório
        if (is_dir($fullName)) {
          // Os diretórios são rastreados recursivamente
          if (static::dropOldFiles($fullName, $days)) {
            self::drop($fullName);
          } else {
            $allDropped = false;
          }
        } else {
          // Verifica se o arquivo é anterior à X dias
          if ($old > (24 * 60 * 60 * $days)) {
            self::drop($fullName);
          } else {
            $allDropped = false;
          }
        }
      }
      
      closedir($directoryHandle);
    } else {
      trigger_error("O diretório $directory é inválido ou inexistente",
        E_USER_WARNING
      );
      
      $allDropped = false;
    }
    
    return $allDropped;
  }
  
  /**
   * Apaga um arquivo ou um diretório vazio.
   *
   * @param string $fileOrDirectory
   *   O nome do arquivo ou diretório a ser excluído
   */
  public static function drop(string $fileOrDirectory): void
  {
    if (is_dir($fileOrDirectory)) {
      @rmdir($fileOrDirectory);
    } else {
      @unlink($fileOrDirectory);
    }
  }
}
