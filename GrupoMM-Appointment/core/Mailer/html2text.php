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
 * Um simples conversor de código HTML para texto puro.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Mailer;

class html2text
{
  /**
   * Remove as tags HTML do string, convertendo para um texto puro
   * válido.
   *
   * @param string $source
   *   O string com o conteúdo do qual removeremos as tags
   * @param string $keep [opcional]
   *   A lista de tags a serem mantidas
   * @param string $expand [opcional]
   *   A lista de tags a serem removidas completamente, ao longo do
   *   nosso conteúdo
   *
   * @return string
   *   O string convertido para texto puro
   */
  public function convert(string $source, string $keep = '',
    string $expand = 'script|style|noframes|select|option'): string
  {
    // Precede o conteúdo com um espaço, por segurança
    $source = ' ' . $source;

    // Inicializa a lógica de tags a serem mantidas
    if (strlen($keep) > 0) {
      // Primeiramente separamos as respectivas tags a serem mantidas
      $tagsToKeep = explode('|', $keep);

      $source = $this->keepTag($source, $tagsToKeep, '<', '[{(');
    }

    // Inicia a remoção
    
    // Removemos quaisquer blocos de comentários
    $source = $this->removeElement($source, '<!--', '-->');

    // Removemos as tags informadas com seus respectivos conteúdos
    if (strlen($expand) > 0) {
      // Primeiramente separamos as respectivas tags a serem localizadas
      $tags = explode('|', $expand);

      // Percorremos as tags informadas
      foreach ($tags as $tag) {
        // Removemos individualmente cada tag de nossa fonte
        $source = $this->removeElement($source, "<$tag", "$tag>");
      }
    }

    // Removemos as tags remanescentes
    $source = $this->removeElement($source, '<', '>');

    // Removemos espaços entre o início das linhas e o texto
    $source = preg_replace("#\h{2,}#m", '', $source);

    // Reduza o número de linhas vazias para duas no máximo
    $source = preg_replace("/\n\s+\n/", "\n\n", $source);
    $source = preg_replace("/[\n]{3,}/", "\n\n", $source);

    // Executa substituições para eliminar caracteres estranhos
    $source = preg_replace_callback_array([
      // Substituí o símbolo de espaço sem quebra pelo espaço
      '/&(nbsp|#160);/i' => function($match) {
        return " ";
      },
      // Substituí o símbolo de aspas duplas pelo caractere
      '/&(quot|rdquo|ldquo|#8220|#8221|#147|#148);/i' => function($match) {
        return '"';
      },
      // Substituí o símbolo de aspas simples pelo caractere
      '/&(apos|rsquo|lsquo|#8216|#8217);/i' => function($match) {
        return "'";
      },
      // Substituí o símbolo de maior que pelo caractere
      '/&gt;/i' => function($match) {
        return ">";
      },
      // Substituí o símbolo de menor que pelo caractere
      '/&lt;/i' => function($match) {
        return "<";
      },
      // Substituí o símbolo de '&' pelo caractere
      '/&(amp|#38);/i' => function($match) {
        return "&";
      },
      // Substituí o símbolo de copyright pelo caractere
      '/&(copy|#169);/i' => function($match) {
        return "(c)";
      },
      // Substituí o símbolo de trademark pelo caractere
      '/&(trade|#8482|#153);/i' => function($match) {
        return "(tm)";
      },
      // Substituí o símbolo de registered pelo caractere
      '/&(reg|#174);/i' => function($match) {
        return "(R)";
      },
      // Substituí o símbolo de traço largo pelo caractere
      '/&(mdash|#151|#8212);/i' => function($match) {
        return "--";
      },
      // Substituí o símbolo de traço médio pelo caractere
      '/&(ndash|minus|#8211|#8722);/i' => function($match) {
        return "-";
      },
      // Substituí o símbolo de bola pelo caractere
      '/&(bull|#149|#8226);/i' => function($match) {
        return "*";
      },
      // Substituí o símbolo de pound pelo caractere
      '/&(pound|#163);/i' => function($match) {
        return '\A3';
      },
      // Substituí o símbolo de euro pelo caractere
      '/&(euro|#8364);/i' => function($match) {
        return 'EUR';
      },
      // Elimina entidades desconhecidas e/ou sem tratamento
      '/&[^&;]+;/i' => function($match) {
        return 'EUR';
      }],
      $source)
    ;

    // Finaliza a lógica de tags a serem mantidas
    if (isset($tagsToKeep)) {
      $source = $this->keepTag($source, $tagsToKeep, '[{(', '<');
    }

    return trim($source);
  }

  /**
   * Remove um elemento (tag) e o seu respectivo conteúdo de nossa fonte
   * informada.
   * 
   * @param string $source
   *   O conteúdo a ser analisado
   * @param string $openTag
   *   A tag de abertura
   * @param string $closeTag
   *   A tag de fechamento
   *
   * @return string
   *   O conteúdo sem o elemento informado
   */
  private function removeElement(string $source, string $openTag,
    string $closeTag): string
  {
    // Processamos o conteúdo enquanto for localizada a respectiva tag
    // sendo analisada
    while (stripos($source, $openTag) > 0) {
      // Localizamos o início e fim de nossa tag
      $startPos = stripos($source, $openTag);
      $endPos   = stripos($source, $closeTag, $startPos);

      // Determinamos o tamanho do conteúdo da tag
      $length = ($endPos + strlen($closeTag)) - $startPos;

      // Determinamos o conteúdo da tag completa
      $fullTagContent = substr($source, $startPos, $length);

      // Eliminamos do conteúdo nossa tag completa
      $source = str_replace($fullTagContent, '', $source);
    }

    return $source;
  }

  /**
   * Função auxiliar que permite manter uma tag, modificando e
   * restaurando seu conteúdo.
   * 
   * @param string $source
   *   A nossa string com o conteúdo
   * @param array $tagsToKeep
   *   Uma matriz com as tags a serem mantidas
   * @param string $delimiter
   *   O delimitador da tag     
   * @param string $newDelimiter
   *   O delimitador modificado
   *
   * @return string
   */
  private function keepTag(string $source, array $tagsToKeep,
    string $delimiter, string $newDelimiter):string
  {
    foreach ($tagsToKeep as $tag) {
      // Modificamos a tag de abertura
      $source = str_replace($delimiter . $tag,
        $newDelimiter . $tag, $source
      );

      // Modificamos a tag de fechamento
      $source = str_replace($delimiter . '/'. $tag,
        $newDelimiter . '/' . $tag, $source
      );
    }

    return $source;
  }
}
