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
 * Uma interface que descreve o registro de um boleto bancário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet;

interface RegisterableBillet
{
  /**
   * Obtém o número de controle interno (da aplicação) para controle da
   * remessa.
   *
   * @return string
   */
  public function getControlNumber(): string;

  /**
   * Obtém o código do campo espécie de documento.
   *
   * @param int $default
   *   O valor padrão
   * @param int $cnabType
   *   O tipo do registro CNAB
   *
   * @return string
   *   O código do campo espécie de documento
   * 
   * @throws InvalidArgumentException
   *   Em caso de informado um tipo CNAB inválido
   */
  public function getKindOfDocumentCode(int $default = 99,
    int $cnabType = 240): string;

  /**
   * Obtém a instrução a ser realizada em relação ao boleto.
   *
   * @return int
   */
  public function getBilletInstruction(): int;
  
  /**
   * Obtém a descrição da instrução a ser aplicada no boleto.
   *
   * @return string
   */
  public function getBilletInstructionName(): string;

  /**
   * Obtém a instrução personalizada a ser aplicada no boleto.
   *
   * @return int|null
   */
  public function getCustomInstruction(): ?int;

  /**
   * Gerar o código do campo livre para as posições de 20 a 44.
   *
   * @param string $freeField
   *
   * @return array
   */
  public function parseFreeField(string $freeField): array;
}
