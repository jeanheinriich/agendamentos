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
 * Interface para o sistema de controle de suspensão de uma conta por
 * falhas sucessivas de autenticação, para permitir bloquear ataques por
 * força bruta. Também permite inserir tempos de bloqueios entre novas
 * tentativas (atrasos), de forma a mitigar este tipo de ação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\FailedLogins;

interface FailedLoginsManagerInterface
{
  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação geral, de forma permitir a
   * mitigação de ataque de força bruta quando proveniente de muitos
   * locais. Usa a contagem de falhas de autenticação por usuário e por
   * IP para determinar um tempo de bloqueio em segundos.
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function globalDelay(): int;

  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação para um determinado endereço IP,
   * de forma a permitir a mitigação de ataque de força bruta proveniente
   * deste endereço. Usa a contagem de falhas de autenticação por
   * endereço IP para determinar um tempo de bloqueio em segundos.
   * 
   * @param string $ipAddress
   *   O endereço IP de onde se originou a solicitação de autenticação
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function ipDelay(string $ipAddress): int;

  /**
   * Método para determinar um atraso até a próxima autenticação em caso
   * de diversas falhas de autenticação para um determinado usuário, de
   * forma permitir a mitigação de ataque de força bruta para quebra de
   * senha deste usuário. Usa a contagem de falhas de autenticação por
   * usuário para determinar um tempo de bloqueio em segundos.
   * 
   * @param mixed $user
   *   O nome ou os dados do usuário
   * 
   * @return int
   *   O tempo (em segundos) de atraso para permitir uma nova tentativa
   */
  public function userDelay($user): int;

  /**
   * Método para determinar a necessidade de suspensão da conta do
   * usuário.
   * 
   * @param string $user
   *   O nome ou os dados do usuário
   * 
   * @return bool
   */
  public function needSuspend($user): bool;
  
  /**
   * Método para registrar falhas de autenticação do usuário.
   * 
   * @param mixed $user
   *   O nome ou os dados do usuário
   * @param string $ipAddress
   *   O endereço IP de onde se originou a solicitação de autenticação
   */
  public function registreFailedLogin($user, string $ipAddress): void;
}
