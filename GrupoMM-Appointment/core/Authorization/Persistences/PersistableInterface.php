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
 * Interface para uma persistência.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Persistences;

interface PersistableInterface
{
  /**
   * Retorna o ID da persistência.
   * 
   * @return int
   *   O ID da persistência
   */
  public function getPersistableId(): int;

  /**
   * Retorna o nome da chave de persistência.
   * 
   * @return string
   *   O nome da chave de persistência
   */
  public function getPersistableKey(): string;

  /**
   * Define o nome da chave de persistência.
   * 
   * @param string $key
   *   O novo nome da chave de persistência
   */
  public function setPersistableKey(string $key): void;
  
  /**
   * Gera um seletor de 32 dígitos para localizar o token na base de
   * dados. Isto evita que o ID do usuário seja enviado para o navegador.
   * 
   * @return string
   *   O seletor
   */
  public function generateSelectorCode(): string;
  
  /**
   * Gera um código de validação aleatório para permitir a persistência
   * segura dos dados do usuário.
   * 
   * @return string
   *   O código de validação
   */
  public function generateValidatorCode(): string;

  /**
   * Retorna o nome da tabela que contém o relacionamento com as
   * informações de persistência para cada usuário.
   * 
   * @return string
   *   O nome da tabela relacionada com as informações de persistência
   */
  public function getPersistableRelationship(): string;
}
