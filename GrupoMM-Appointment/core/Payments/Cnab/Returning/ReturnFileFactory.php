<?php
/*
 * This file is part of the payment's API library.
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
 * Uma classe para fabricar o interpretador correto do arquivo de
 * retorno em função do seu conteúdo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning;

use Core\Helpers\Path;
use RuntimeException;

class ReturnFileFactory
{
  // Mapa das classes dos bancos e seus códigos
  protected static $classMap = array(
      1 => 'BancoDoBrasil',
     33 => 'Santander',
     70 => 'BRB',
     90 => 'Unicred',
    104 => 'Caixa',
    237 => 'Bradesco',
    341 => 'Itau',
  );

  /**
   * Retorna a instância de um leitor para um banco através do seu
   * respectivo código.
   *
   * @param int $bankCode
   *   O código do banco
   * @param int $cnabType
   *   O tipo do registro Cnab
   * @param string $filename
   *   O nome do arquivo
   *
   * @return Core\Payments\Cnab\ReturnFile
   *   A instância do interpretador de arquivos de retorno
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um código de banco não suportado
   */
  public static function loadBankFromCode(int $bankCode, int $cnabType,
    string $filename)
  {
    if (!isset(static::$classMap[$bankCode])) {
      throw new InvalidArgumentException(
        sprintf('O banco de código "%d" não é suportado.', $bankCode)
      );
    }

    $name = static::$classMap[$bankCode];
    $class = __NAMESPACE__ . '\\Cnab' . $cnabType . '\\' . $name;
    
    if (!class_exists($class)) {
      throw new InvalidArgumentException(
        sprintf('O banco "%s" não é suportado.', $name)
      );
    }
    
    return new $class($filename);
  }

  /**
   * Cria um interpretador de arquivos de retorno no padrão Cnab.
   * 
   * @param string $filename
   *   O nome do arquivo
   *
   * @return ReturnFile
   * 
   * @throws RuntimeException
   *   Em caso de erro
   */
  public static function make(string $filename)
  {
    $cnabFile = new Path($filename);
    if (!$cnabFile->exists() || !$cnabFile->isFile()) {
      throw new RuntimeException("O arquivo {$filename} não existe");
    }

    // Carrega o conteúdo do arquivo
    $content = file($cnabFile);

    // Obtém o código do banco da primeira linha (cabeçalho)
    $bankCode = self::getBankCode($content[0]);

    // Obtém o tipo do arquivo Cnab
    $cnabType = self::getRowLength($content[0]);

    if ($bankCode === '000') {
      // Não é um arquivo de retorno válido, então interrompe
      throw new RuntimeException("O arquivo '{$filename}' não é um "
        . "arquivo de retorno no padrão Cnab válido"
      );
    }

    // Obtemos a instância da classe para leitura do banco
    return self::loadBankFromCode($bankCode, $cnabType, $filename);
  }

  /**
   * Obtém o tamanho da linha do registro do arquivo.
   * 
   * @param string $row
   *   O conteúdo da linha
   * 
   * @return int
   */
  protected static function getRowLength(string $row): int
  {
    return mb_strlen(rtrim($row, "\r\n"));
  }

  /**
   * Valida se o conteúdo informado corresponde ao registro de cabeçalho
   * (header) de um arquivo de retorno no padrão Cnab e obtém o código
   * do banco emissor.
   *
   * @param string $row
   *   O conteúdo da linha
   *
   * @return string
   */
  public static function getBankCode(string $row): string
  {
    $length = self::getRowLength($row);
    $bankCode = '000';

    switch ($length) {
      case 240:
        // Determina se o registro é válido
        if (mb_substr($row, 142, 1) === '2') {
          $bankCode = mb_substr($file_content[0], 0, 3);
        }

        break;
      case 400:
        // Determina em função do tipo do registro
        if (mb_substr($row, 0, 9) === '02RETORNO') {
          $bankCode = mb_substr($row, 76, 3);
        }

        break;
      default:
        // Não é um arquivo com registro no tamanho experado
        break;
    };

    return $bankCode;
  }
}
