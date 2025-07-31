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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * exibição de uma barra de progresso em modo console que outras classes
 * podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Console;

use Carbon\Carbon;
use Core\Console\Console;

trait ProgressBarTrait
{
  /**
   * O controlador do dispositivo de saída do conteúdo.
   *
   * @var Core\Console\Console
   */
  protected $console;

  /**
   * Os caracteres utilizados para mostrar um caractere giratório
   * (spinner) para indicar que a rotina está trabalhando.
   * 
   * @var string
   */
  protected $spinnerChars = "/-\|";

  /**
   * O controle de rotação do caractere giratório.
   * 
   * @var integer
   */
  protected $spinnerLoop = 0;

  /**
   * O tempo inicial da execução do progresso.
   * 
   * @var float
   */
  protected $initialTime;

  /**
   * Recupera o tempo estimado que falta para concluir (em segundos) de
   * uma forma legível ao usuário.
   * 
   * @param int $seconds
   *   O tempo em segundos
   * 
   * @return string
   *   O tempo de maneira legível
   */
  protected function getHumanTime(int $seconds): string
  {
    $humanTime = '0';

    if ($seconds > 0) {
      // Convertemos o tempo (em segundos) em um valor legível ao
      // usuário
      $now      = Carbon::now()->locale('pt_BR');
      $interval = Carbon::now()->locale('pt_BR')  
        ->addSeconds($seconds)  
      ; 
      $humanTime = $now->shortAbsoluteDiffForHumans(
        $interval, 4)
      ;
    }

    return $humanTime;
  }

  /**
   * Obtém o caractere giratório.
   *
   * @return string
   */
  protected function getSpinnerChar(): string
  {
    $this->spinnerLoop++;
    if ($this->spinnerLoop === strlen($this->spinnerChars)) {
      $this->spinnerLoop = 0;
    }

    return substr($this->spinnerChars, $this->spinnerLoop, 1);
  }

  /**
   * Gera uma barra de progresso.
   * 
   * @param float $done
   *   A porcentagem concluída.
   * @param float $total
   *   O total
   * @param string $infoMessage
   *   Uma mensagem de aviso
   * @param bool $error
   *   A flag indicativa de erro
   * @param bool $finish
   *   A flag indicativa de finalização do progresso
   * @param bool $showTimeLeft
   *   A flag indicativa de exibir o tempo faltante
   * @param int $width
   *   A largura da barra
   */
  protected function progressBar(float $done, float $total,
    string $infoMessage = "", bool $error = false, bool $finish = false,
    bool $showTimeLeft = false, int $width = 25): void
  {
    if (!isset($this->console)) {
      $this->console = new Console();
    }

    // Determinamos a largura da tela
    $screenWidth = exec('tput cols'); // A largura da tela

    $showInfo = true;
    $showProgressBar = true;
    if ($screenWidth < 60) {
      // Deixamos de exibir algumas partes
      $showInfo = false;

      if ($screenWidth < 18) {
        // Não exibimos a barra de progresso
        $showProgressBar = false;
      }
    }

    if ($showProgressBar) {
      // Verificamos a necessidade de reduzirmos o tamanho máximo da
      // barra de progresso em função do tamanho da tela
      $barWidth      = $width + 2; // O tamanho da barra mais separador
      $progressWidth = 10;         // O tamanho da percentagem e spinner
      if ($showInfo) {
        $infoWidth = 40;
      } else {
        $infoWidth = 0;
      }
      if ( (10 + $barWidth + 1 + $infoWidth) > $screenWidth ) {
        $width = $screenWidth - (10 + 2 + $infoWidth + 1);
        if ($width < 7) {
          $width = 7;
        }
      }
    }

    $timeToEnd = '';
    if ($done > 0) {
      if ($done < $total) {
        // Determina qual a percentagem concluída
        $perc = round(($done * 100) / $total, 2);
      } else {
        // Limita a percentagem à 100%
        $perc = 100;
      }

      // Determina a largura da barra
      $barSize = round(($width * $perc) / 100);

      if ($showTimeLeft) {
        if (($done < $total) && ($perc > 0)) {
          // Determina quando tempo ainda falta
          $currentTime   = microtime(true);
          $executionTime = $currentTime - $this->initialTime;
          $seconds       = (int) round($executionTime);
          $percLeft      = 100 - $perc;
          $estimatedTime = intval(($executionTime / $perc) * $percLeft);
          $timeToEnd     = sprintf('(%s) ',
            $this->getHumanTime($estimatedTime))
          ;
        }
      }
    } else {
      $perc = 0;
      $barSize = 0;

      if ($showTimeLeft) {
        // Armazena o tempo do início do progresso
        $this->initialTime = microtime(true);
      }
    }

    $spinnerChar = $this->getSpinnerChar();

    // Determinamos as cores em função do estado
    $barColor = ($error)
      ? '<red>'
      : '<white>'
    ;
    $infoColor = ($error)
      ? '<lightRed>'
      : '<blue>'
    ;

    // Montamos o conteúdo a ser exibido
    $percentage = sprintf("<lightYellow>%6.2f", $perc);
    $spinner = sprintf("<lightWhite>%s", $spinnerChar);
    $doneBar = ($barSize > 0)
      ? str_repeat('▓', $barSize)
      : ''
    ;
    $remainingBar = (($width - $barSize) > 0)
      ? str_repeat("░", ($width - $barSize))
      : ''
    ;
    $bar = ($showProgressBar)
      ? "<lightWhite>▐{$barColor}{$doneBar}{$remainingBar}<lightWhite>▌"
      : ""
    ;
    $info = ($showInfo)
      ? sprintf("{$infoColor} %-40s", $infoMessage)
      : ''
    ;
    $content = "<bgBlack>"
      . "{$percentage}% {$spinner} {$bar}{$timeToEnd}{$info}\r"
    ;

    $this->console->inline($content);

    if ($error || $finish) {
      // Se for um erro ou estamos encerrando o progresso, avança a
      // linha
      $this->console->out();
      $this->console->out();
    }
  }
}