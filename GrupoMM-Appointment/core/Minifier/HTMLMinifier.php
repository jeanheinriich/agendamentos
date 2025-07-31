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
 * Classe responsável por reduzir um conteúdo que contém um código HTML,
 * removendo caracteres desnecessários e reduzir o tamanho do conteúdo
 * a ser enviado ao navegador, sem alterar sua funcionalidade.
 */

namespace Core\Minifier;

use Exception;
use RuntimeException;

class HTMLMinifier
  extends AbstractMinifier
{
  /**
   * O conteúdo reduzido.
   * 
   * @var string
   */
  protected $output = '';

  protected $build = [];
  protected $skip = 0;
  protected $skipName = '';
  protected $head = false;

  /**
   * Os elementos HTML, separados em grupos:
   *   skip: elementos que não deverão ser modificados
   *   inline: elementos que podem ser colocados em linha
   *   hard: elementos para os quais podemos fazer uma redução adicional
   * 
   * @var array
   */
  protected $elements = [
    'skip' => [
      'code',
      'pre',
      'script',
      'textarea',
    ],
    'inline' => [
      'a',
      'abbr',
      'acronym',
      'b',
      'bdo',
      'big',
      'br',
      'cite',
      'code',
      'dfn',
      'em',
      'i',
      'img',
      'kbd',
      'map',
      'object',
      'samp',
      'small',
      'span',
      'strong',
      'sub',
      'sup',
      'tt',
      'var',
      'q'
    ],
    'hard' => [
      '!doctype',
      'body',
      'html'
    ]
  ];

  /**
   * Analisa uma string que contém um código HTML, removendo caracteres
   * desnecessários para reduzir o código sem alterar sua
   * funcionalidade.
   *
   * @param string $source
   *   O conteúdo a ser reduzido
   *
   * @return string
   *   O conteúdo reduzido (minificado)
   * 
   * @throws Exception
   *   Em caso de erro na redução
   */
  public function minify(string $source): string
  {
    // Inicialmente, removemos quaisquer comentários no código HTML
    $source = $this->removeComments($source);

    // Agora percorremos o conteúdo, analisando individualmente cada
    // tag
    while (!empty($source)) {
      // Retiramos a primeira tag. Em $parts teremos em '0' a tag a ser
      // analisada e em '1' o restante do código
      $parts = explode('<', $source, 2);

      // Fazemos a análise desta TAG
      $this->parse($parts[0]);

      // Associamos ao $source o restante do conteúdo para prosseguir a
      // análise, se necessário
      $source = (isset($parts[1])) ? $parts[1] : '';
    }

    return $this->output;
  }

  /**
   * Remove quaisquer comentários do código HTML, reduzindo seu tamanho.
   * 
   * @param string $source
   *   O código HTML a ser reduzido
   * 
   * @return string
   *   O conteúdo reduzido
   */
  protected function removeComments(string $source = ''): string
  {
    $this->debug("Removendo comentários");

    return preg_replace('/(?=<!--)([\s\S]*?)-->/', '', $source);
  }

  /**
   * A função que percorre o HTML removendo caracteres desnecessários
   * @param string &$tag
   *   A tag HTML que estamos analisando
   */
  protected function parse(string &$tag): void
  {
    $this->debug("Analisando a tag [{$tag}]");
    
    // Retiramos o conteúdo da tag
    $parts = explode('>', $tag);
    $tagContent = $parts[0];

    if (!empty($tagContent)) {
      // Localiza o nome da tag à partir do conteúdo
      $tagName = $this->findTagName($tagContent);

      // Elimina os caracteres e espaços desnecessários
      $tagMinified = $this->clean($tagContent, $tag, $tagName);

      // Determina o tipo da tag (abertura ou fechamento)
      $tagType = $this->getTagType($tagMinified);

      // Verifica se esta é uma tag de cabeçalho
      if ($tagName == 'head') {
        $this->debug("Tag de cabeçalho");
        $this->head = ($tagType === 'open')?true:false;
      }

      // Monta a estrutura a ser convertida no html minificado
      $this->build[] = [
        'name' => $tagName,
        'content' => $tagMinified,
        'type' => $tagType
      ];

      // Pula elementos que não podem ser modificados
      $this->setSkip($tagName, $tagType);

      if (!empty($tagContent)) {
        // Verifica se temos algum conteúdo adicional nesta tag
        $content = (isset($parts[1])) ? trim($parts[1]) : '';
        if ($content !== '') {
          // Temos o conteúdo adicional
          $this->build[] = [
            'content' => $this->compact($content, $tagName),
            'type' => 'content'
          ];
        }
      }

      // Geramos o conteúdo HTML
      $this->buildHtml();
    }
  }

  /**
   * Retorna o tipo da tag, ou seja, se é de abertura ou fechamento.
   * 
   * @param string $tagContent
   *   O conteúdo da tag que estamos analisando
   * 
   * @return string
   *   O tipo da tag (abertura ou fechamento)
   */
  protected function getTagType(string $tagContent)
  {
    return (substr($tagContent, 1, 1) == '/') ? 'close' : 'open';
  }

  /**
   * Realiza a eliminação de espaços e caracteres desnecessários na tag.
   * 
   * @param string $tagContent
   *   O conteúdo da tag que estamos analisando
   * @param string $originalContent
   *   A parte restante da tag
   * @param string $tagName
   *   O nome da tag (Ex: 'p' ou 'img')
   * 
   * @return string
   */
  protected function clean(string $tagContent, string $originalContent,
    string $tagName)
  {
    $this->debug("Limpando a tag html");

    $tagContent = $this->stripWhitespace($tagContent);
    $tagContent = $this->addChevrons($tagContent, $originalContent);
    $tagContent = $this->removeSelfSlash($tagContent);
    $tagContent = $this->removeMeta($tagContent, $tagName);

    return $tagContent;
  }

  /**
   * Elimina espaços em branco do elemento.
   * 
   * @param string $tagContent
   *   O conteúdo da tag sendo analisada
   * 
   * @return string
   *   O conteúdo modificado
   */
  protected function stripWhitespace(string $tagContent): string
  {
    $this->debug("Eliminando espaços desnecessários");

    if ($this->skip == 0) {
      $tagContent = preg_replace('/\s+/', ' ', $tagContent);
    }

    return trim($tagContent);
  }

  /**
   * Adicionar os caracteres "<" e ">" que delimitam uma tag HTML.
   * 
   * @param string $tagContent
   *   O conteúdo da tag html modificada
   * @param string $originalContent
   *   O conteúdo original da tag sem modificações
   * 
   * @return string
   *   O conteúdo modificado
   */
  protected function addChevrons(string $tagContent,
    string $originalContent): string
  {
    $this->debug("Adicionando delimitadores '<' e '>'");

    if (empty($tagContent)) {
      return $tagContent;
    }

    // Determinamos se devemos realizar o fechamento da tag
    $char = ($this->contains('>', $originalContent)) ? '>' : '';
    $tagContent = '<' . $tagContent . $char;

    return $tagContent;
  }

  /**
   * Remover barra própria desnecessária.
   * 
   * @param string $tagContent
   *   O conteúdo da tag sendo analisada
   * 
   * @return string
   *   O conteúdo modificado
   */
  protected function removeSelfSlash(string $tagContent): string
  {
    $this->debug("Substituíndo '/>' por '>'");

    if (substr($tagContent, -3) == ' />') {
      $tagContent = substr($tagContent, 0, -3) . '>';
    }

    return $tagContent;
  }

  /**
   * Remova informações meta desnecessárias da tag.
   * 
   * @param string $tagContent
   *   O conteúdo da tag sendo analisada
   * @param string $tagName
   *   O nome da tag
   * 
   * @return string
   *   O elemento sem informações meta desnecessárias
   */
  protected function removeMeta(string $tagContent, string $tagName)
  {
    $this->debug("Removendo informações meta desnecessárias");

    if ($tagName == 'style') {
      // Eliminamos a informação de type='text/css' de tags de estilo
      $tagContent = str_replace(
        [
            ' type="text/css"',
            "' type='text/css'"
        ],
        ['', ''],
        $tagContent
      );
    } elseif ($tagName == 'script') {
      // Eliminamos a informação de type='text/javascript' de tags de
      // script
      $tagContent = str_replace(
        [
            ' type="text/javascript"',
            " type='text/javascript'"
        ],
        ['', ''],
        $tagContent
      );
    }

    return $tagContent;
  }

  /**
   * Compacta o conteúdo em função de sua característica.
   * 
   * @param string $content
   *   O conteúdo a ser compactado
   * @param string $tagName
   *   O nome da tag
   * 
   * @return string
   *   O conteúdo modificado
   */
  protected function compact(string $content, string $tagName): string
  {
    if ($this->skip != 0) {
      $tagName = $this->skipName;
    } else {
      $content = preg_replace('/\s+/', ' ', $content);
    }

    if (in_array($tagName, $this->elements['skip'])) {
      return $content;
    } elseif (in_array($tagName, $this->elements['hard']) ||
      $this->head) {
      return $this->minifyHard($content);
    } else {
      return $this->minifyKeepSpaces($content);
    }
  }

  /**
   * Constrói o código HTML à partir do conteúdo já minificado.
   */
  protected function buildHtml(): void
  {
    foreach ($this->build as $build) {
      if (!empty($this->options['collapse_whitespace'])) {
        if (strlen(trim($build['content'])) == 0) {
          continue;
        } elseif ($build['type'] != 'content'
                  && !in_array($build['name'], $this->elements['inline'])) {
          trim($build['content']);
        }
      }

      $this->output .= $build['content'];
    }

    $this->build = [];
  }

  /**
   * Encontre o nome da tag analisando o conteúdo.
   * 
   * @param string $tag
   *   O conteúdo da tag a ser analisada
   * 
   * @return string
   *   O nome da tag
   */
  protected function findTagName(string $tag): string
  {
    // Pegamos a primeira parte
    $tagName = explode(" ", $tag, 2)[0];

    // Eliminamos o caractere de fechamento
    $tagName = explode(">", $tagName, 2)[0];

    // Eliminamos retorno de carro
    $tagName = explode("\n", $tagName, 2)[0];

    // Obtemos apenas o texto
    $tagName = preg_replace('/\s+/', '', $tagName);

    // Colocamos o nome da tag em minúsculo
    $tagName = strtolower(str_replace('/', '', $tagName));

    return $tagName;
  }

  /**
   * Define se devemos pular os elementos que são bloqueados para
   * minimização.
   * 
   * @param string $tagName
   *   O nome da tag
   * @param string $tagType
   *   O tipo do elemento (abertura ou fechamento)
   */
  protected function setSkip(string $tagName, string $tagType): void
  {
    foreach ($this->elements['skip'] as $element) {
      if ($element == $tagName && $this->skip == 0) {
        $this->skipName = $tagName;
      }
    }
    if (in_array($tagName, $this->elements['skip'])) {
      if ($tagType == 'open') {
        $this->skip++;
      }
      if ($tagType == 'close') {
        $this->skip--;
      }
    }
  }

  /**
   * Reduza tudo, até mesmo os espaços entre os elementos.
   * 
   * @param string $tagContent
   *   O conteúdo da tag html modificada
   * 
   * @return string
   *   A tag HTML reduzida
   */
  protected function minifyHard(string $tagContent): string
  {
    $tagContent = preg_replace('!\s+!', ' ', $tagContent);
    $tagContent = trim($tagContent);

    return $tagContent;
  }

  /**
   * Remove espaços desnecessários que ainda tenham permanecido.
   * 
   * @param string $tagContent
   *   O conteúdo da tag html modificada
   * 
   * @return string
   *   A tag sem espaços desnecessários
   */
  protected function minifyKeepSpaces(string $tagContent): string
  {
    return preg_replace('!\s+!', ' ', $tagContent);
  }
}
