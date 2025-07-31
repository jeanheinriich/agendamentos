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
 * Classe factory para a criação de instâncias de boletos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;

use Core\Payments\BankingBillet\BankingBillet;
use InvalidArgumentException;

class BankingBilletFactory
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
   * Retorna a instância de um banco através do seu respectivo código.
   *
   * @param int $bankCode
   *   O código do banco
   * @param array $params
   *   Os parâmetros a serem passados
   *
   * @return BankingBillet
   *   A instância do gerador de boletos
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um código de banco não suportado
   */
  public static function loadBankFromCode(
    int $bankCode,
    array $params = []
  ): BankingBillet
  {
    if (!isset(static::$classMap[$bankCode])) {
      throw new InvalidArgumentException(
        sprintf('O banco de código "%d" não é suportado.', $bankCode)
      );
    }
    
    return static::loadBankFromName(static::$classMap[$bankCode],
      $params
    );
  }

  /**
   * Retorna a instância de um banco através do seu nome.
   *
   * @param string $name
   *   O nome do banco
   * @param array $params
   *   Os parâmetros a serem passados
   *
   * @return BankingBillet
   *   A instância do gerador de boletos
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um código de banco não suportado
   */
  public static function loadBankFromName(
    string $name,
    array $params = []
  ): BankingBillet
  {
    $class = __NAMESPACE__ . '\\Banks\\' . $name;
    
    if (!class_exists($class)) {
      throw new InvalidArgumentException(
        sprintf('O banco "%s" não é suportado.', $name)
      );
    }
    
    return new $class($params);
  }
}
