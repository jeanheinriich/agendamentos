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
 * A interface para comandos de depuração.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Debugger;

interface DebuggerInterface
{
  /**
   * Registra um evento quando o sistema está inutilizável. Nesta caso a
   * mensagem de log é gerada e o aplicativo é encerrado.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function emergency(string $message, array $context = []): void;

  /**
   * Registra um evento quando uma ação deve ser tomada imediatamente,
   * como, por exemplo: O site inteiro está inativo, ou o banco de dados
   * não está disponível, etc. Isso deve acionar os alertas do SMS e
   * acordá-lo.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function alert(string $message, array $context = []): void;

  /**
   * Registra um evento quando ocorre uma condição crítica.
   * Exemplo: um componente do aplicativo está indisponível, ou ocorreu
   * uma exceção inesperada.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function critical(string $message, array $context = []): void;

  /**
   * Registra eventos de erros em tempo de execução, e que não requerem
   * ação imediata, mas geralmente devem ser registrados e monitorados.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function error(string $message, array $context = []): void;

  /**
   * Registra ocorrências excepcionais que não são erros. Exemplo: uso
   * de APIs obsoletas, mau uso de uma API, coisas indesejáveis que não
   * estão necessariamente erradas.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function warning(string $message, array $context = []): void;

  /**
   * Registra eventos normais, mas significativos.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function notice(string $message, array $context = []): void;

  /**
   * Registra eventos interessantes, como por exemplo: usuário
   * autentica-se, registros de logs do SQL, etc.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function info(string $message, array $context = []): void;
  
  /**
   * Regista informações detalhadas sobre depuração.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function debug(string $message, array $context = []): void;
}
