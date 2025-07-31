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
 * Uma classe para erros de blocos ausentes num template Twig.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer\Twig;

use RuntimeException;

class MissingBlockException
  extends RuntimeException
{
  /**
   * Lança uma nova exceção por ausência de um determinado bloco no
   * template do corpo do e-mail.
   * 
   * @param array $blockNames
   *   Uma matriz com os nomes dos blocos exigidos
   *
   * @return MissingBlockException
   */
  public static function missingBlock(array $blockNames): MissingBlockException
  {
    $blocksName = implode(',', $blockNames);
    $errorMsg = ($blocksName === '')
      ? 'Nenhum bloco encontrado.'
      : 'Blocos encontrados: ' . $blocksName . '.'
    ;

    return new self(""
      . "Seu modelo precisa de um bloco de assunto ('subject') e um "
      . "bloco de corpo formatado em HTML ('body_html') e/ou formatado "
      . "em texto puro ('body_text'). " . $errorMsg
    );
  }
}
