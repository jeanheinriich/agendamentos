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
 * Essa é uma trait (característica) simples do sistema de log que as
 * classes incapazes de estender o AbstractLogger (porque estendem outra
 * classe, etc.) podem incluir.
 * 
 * Ele simplesmente delega todos os métodos específicos do nível de log
 * ao método `log` para reduzir o código padrão que um simples sistema
 * de log necessitaria.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Logger;

use Psr\Log\LoggerInterface;

trait LoggerTrait
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
  public function emergency(string $message, array $context = []): void
  {
    $this->log(LogLevel::EMERGENCY, $message, $context);
  }

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
  public function alert(string $message, array $context = []): void
  {
    $this->log(LogLevel::ALERT, $message, $context);
  }

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
  public function critical(string $message, array $context = []): void
  {
    $this->log(LogLevel::CRITICAL, $message, $context);
  }

  /**
   * Registra eventos de erros em tempo de execução, e que não requerem
   * ação imediata, mas geralmente devem ser registrados e monitorados.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function error(string $message, array $context = []): void
  {
    $this->log(LogLevel::ERROR, $message, $context);
  }

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
  public function warning(string $message, array $context = []): void
  {
    $this->log(LogLevel::WARNING, $message, $context);
  }

  /**
   * Registra eventos normais, mas significativos.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function notice(string $message, array $context = []): void
  {
    $this->log(LogLevel::NOTICE, $message, $context);
  }

  /**
   * Registra eventos interessantes, como por exemplo: usuário
   * autentica-se, registros de logs do SQL, etc.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function info(string $message, array $context = []): void
  {
    $this->log(LogLevel::INFO, $message, $context);
  }

  /**
   * Regista informações detalhadas sobre depuração.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function debug(string $message, array $context = []): void
  {
    $this->log(LogLevel::DEBUG, $message, $context);
  }

  /**
   * Registra eventos com um nível arbitrário.
   *
   * @param mixed $level
   *   O nível do evento
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   * 
   * @return void
   *
   * @throws \Psr\Log\InvalidArgumentException
   */
  protected function log($level, string $message,
    array $context = []): void
  {
    if ($this->logger instanceof LoggerInterface) {
      if (method_exists($this->logger, 'log')) {
        $this->logger->log($level, $message, $context);
      }
    }
  }
}
