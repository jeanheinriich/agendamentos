<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * O controlador do comando de ajuda do aplicativo de ERP para execução
 * de tarefas em linha de comando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace App\Commands;

use Core\Console\Command;

final class HelpCommand
  extends Command
{
  /**
   * O comando a ser executado
   *
   * @param string[] $aArgs {@inheritdoc}
   *
   * @return void
   */
  public function command(array $args)
  {
    // Registra a execução
    $this->notice("Exibindo a ajuda para os comandos em modo console.",
      [ ]);
    
    $this->console->out("<white>Uso: <lightWhite><bold>php ",
      $this->application,
      " <lightYellow><comando> <lightCyan>[opções]"
    );
    $this->console->out();
    $this->console->out("<white>Os comandos disponíveis são:");
    $this->console->out("<lightYellow>  help: <white>exibe esta ",
      "mensagem de ajuda"
    );
    $this->console->out("<lightYellow>  sync: <white>sincroniza os ",
      "dados utilizando a integração"
    );
    $this->console->out();
  }
}