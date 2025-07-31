<?php
/*
 * This file is part of Extension Library.
 *
 * Copyright (c) 2018 Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * Portions Copyright (c) 2016-2017 Alexis Wurth <awurth.dev@gmail.com>
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
 * Uma interface para o sistema de validação dos campos de um formulário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * @author Alexis Wurth <awurth.dev@gmail.com>
 */

namespace Core\Validation;

use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Rules\AllOf;

interface ValidatorInterface
{
  /**
   * Valida parâmetros de solicitação, uma matriz ou propriedades de
   * objetos.
   *
   * @param mixed $input
   *   Os valores de entrada
   * @param array $rules
   *   As regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * 
   * @return $this
   *   A instância do validador
   *
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   */
  public function validate($input, array $rules, ?string $group = null,
    array $messages = [], $default = null): self;
  
  /**
   * Determina se não há erros de validação.
   * 
   * @return boolean
   */
  public function isValid(): bool;
  
  /**
   * Obtém um erro para o parâmetro informado.
   *
   * @param string $key
   *   A chave (nome do campo)
   * @param mixed $index (opcional)
   *   O índice do valor (para o caso de estarmos lidando com uma matriz
   *   de valores)
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return string
   *   A mensagem de erro de validação
   */
  public function getError(string $key, $index = null,
    ?string $group = null): string;
  
  /**
   * Obtém todos os erros.
   *
   * @param string $key (opcional)
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   *
   * @return array
   *   Uma matriz com os erros de validação
   */
  public function getErrors(?string $key = null,
    ?string $group = null): array;

  /**
   * Obtém o primeiro erro para o parâmetro.
   *
   * @param string $key
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return string
   *   A mensagem de erro
   */
  public function getFirstError(string $key,
    ?string $group = null): string;
  
  /**
   * Obtém um valor validado para o parâmetro informado.
   *
   * @param string $key
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return mixed
   *   O valor do campo
   */
  public function getValue(string $key, ?string $group = null);
  
  /**
   * Obtém todos os valores validados.
   *
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   *
   * @return array
   *   Uma matriz com os valores validados
   */
  public function getValues(?string $group = null);
}
