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
 * Um formatador de textos no padrão de cores ANSI para consoles. Os
 * modificadores de estilos devem ser usados após a definição de cor
 * para que tenham efeito.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Console\Formatters;

class Ansi
  extends PlainText
  implements Formatter
{
  /**
   * A flag que indica se os códigos de cores estão habilitados para
   * este sistema. Se nulo, o sistema irá identificar o suporte na
   * primeira execução.
   * 
   * @var null|boolean
   */
  private static $isSupported = null;

  /**
   * Formata as cores no padrão ANSI
   *
   * @var array
   */
  protected $tags = [
    // Cores padrões
    '<black>'          => "\033[0;30m",
    '<red>'            => "\033[0;31m",
    '<green>'          => "\033[0;32m",
    '<yellow>'         => "\033[0;33m",
    '<blue>'           => "\033[0;34m",
    '<magenta>'        => "\033[0;35m",
    '<cyan>'           => "\033[0;36m",
    '<white>'          => "\033[0;37m",

    // Cores escuras
    '<darkGray>'       => "\033[1;90m",
    '<darkRed>'        => "\033[0;31m",
    '<darkGreen>'      => "\033[0;32m",
    '<darkYellow>'     => "\033[0;33m",
    '<darkBlue>'       => "\033[0;34m",
    '<darkMagenta>'    => "\033[0;35m",
    '<darkCyan>'       => "\033[0;36m",
    '<darkWhite>'      => "\033[0;37m",

    // Cores claras
    '<lightGray>'      => "\033[0;37m",
    '<lightRed>'       => "\033[0;91m",
    '<lightGreen>'     => "\033[0;92m",
    '<lightYellow>'    => "\033[0;93m",
    '<lightBlue>'      => "\033[0;94m",
    '<lightMagenta>'   => "\033[0;95m",
    '<lightCyan>'      => "\033[0;96m",
    '<lightWhite>'     => "\033[0;97m",

    // Fundos Normais
    '<bgBlack>'        => "\033[49m",
    '<bgGray>'         => "\033[40m",
    '<bgRed>'          => "\033[41m",
    '<bgGreen>'        => "\033[42m",
    '<bgYellow>'       => "\033[43m",
    '<bgBlue>'         => "\033[44m",
    '<bgMagenta>'      => "\033[45m",
    '<bgCyan>'         => "\033[46m",
    '<bgWhite>'        => "\033[47m",

    // Fundos claros
    '<bgDarkGray>'     => "\033[100m",
    '<bgLightRed>'     => "\033[101m",
    '<bgLightGreen>'   => "\033[102m",
    '<bgLightYellow>'  => "\033[103m",
    '<bgLightBlue>'    => "\033[104m",
    '<bgLightMagenta>' => "\033[105m",
    '<bgLightCyan>'    => "\033[106m",
    '<bgLightWhite>'   => "\033[107m",

    // Modificadores de estilo
    '<reset>'          => "\033[0m",
    '<bold>'           => "\033[1m",
    '<dark>'           => "\033[2m",
    '<italic>'         => "\033[3m",
    '<underline>'      => "\033[4m",
    '<blink>'          => "\033[5m",
    '<reverse>'        => "\033[7m",
    '<concealed>'      => "\033[8m",
    '<strikeout>'      => "\033[9m"
  ];

  /**
   * Analisa o texto passado, interpretando as respectivas tags, e
   * devolvendo o texto formatado.
   *
   * @param string $text
   *   O texto a ser analisado
   *
   * @return string
   *   O texto formatado
   */
  public function format(string $text): string
  {
    return $this->tagsToColors($text);
  }

  /**
   * Esta é a função principal para converter tags em códigos de cores
   * ANSI. Por segurança, esta função sempre adiciona um <reset> no
   * final, caso contrário, o console pode aderir permanentemente às
   * cores que você usou.
   *
   * @param string $text
   *   O texto a ser analisado
   * 
   * @return string
   *   O texto com as tags substituídas pelos respectivos códigos de
   *   cores no padrão ANSI (se suportado)
   */
  public function tagsToColors(string $text): string
  {
    if (null === self::$isSupported) {
      // Determinamos se temos suporte ao modo de cores ANSI
      self::$isSupported = $this->hasSupport();
    }

    if (!self::$isSupported) {
      // Não há suporte ao padrão de cores ANSI, então devolve um texto
      // plano
      return $this->cleanTags($text);
    }

    // Sempre adicionamos um <reset> no final de cada string para que
    // qualquer saída seguinte não continue com o mesmo estilo
    $text .= '<reset>';

    return str_replace(
      array_keys($this->tags), $this->tags, $text
    );
  }

  /**
   * Determina se o modo de cores Ansi é suportado.
   * 
   * @return bool
   */
  protected function hasSupport(): bool
  {
    if (DIRECTORY_SEPARATOR === '\\') {
      if (function_exists('sapi_windows_vt100_support')
          && @sapi_windows_vt100_support(STDOUT)) {
        return true;
      } elseif (getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON') {
        return true;
      }

      return false;
    } else {
      return function_exists('posix_isatty')
        && @posix_isatty(STDOUT)
      ;
    }
  }
}