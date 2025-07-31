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
 * A interface para o sistema de sincronismo de dados usando uma API de
 * um serviço genérico de um provedor externo.
 */

namespace Core\HTTP;

interface Job
{
  /**
   * O método responsável por adicionar uma nova tarefa a ser executada
   * e que será responsável por qualquer requisição e a respectiva
   * análise dos resultados.
   *
   * @param Task $task
   *   A tarefa a ser executada
   */
  public function addTask(Task $task): void;

  /**
   * O método responsável por preparar os parâmetros de nossa requisição
   * antes do início do sincronismo.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros adicionais necessários (Opcional).
   */
  public function prepareParameters(array $parameters = []): void;

  /**
   * O método responsável por definir um tempo de atraso entre
   * requisições, limitando a quantidade de requisições por minuto.
   *
   * @param int $delay
   *   O tempo de atraso (em segundos) entre cada requisição.
   */
  public function setDelay(int $delay): void;

  /**
   * O método responsável por executar cada tarefa definida.
   */
  public function execute(): void;
}
