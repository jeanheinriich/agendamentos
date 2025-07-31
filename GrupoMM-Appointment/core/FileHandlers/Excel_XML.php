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
 * Uma biblioteca de exportação simples para despejar dados de uma
 * matriz em um formato legível no Excel. Suporta OpenOffice Calc
 * também.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FileHandlers;

use Exception;

class Excel_XML
{
  /**
   * Cabeçalho MicrosoftXML para Excel.
   * 
   * @var string
   */
  const header = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";

  /**
   * Rodapé MicrosoftXML para Excel.
   * 
   * @var string
   */
  const footer = "</Workbook>";

  /**
   * Os dados da folha de trabalho.
   * 
   * @var array
   */
  private $worksheetData;

  /**
   * O conteúdo de saída.
   * 
   * @var string
   */
  private $output;

  /**
   * Codificação a ser usada.
   * 
   * @var string
   */
  private $encoding;

  /**
   * O construtor de nossa classe. Instancia a classe permitindo uma
   * codificação definida pelo usuário.
   *
   * @param string $encoding
   *   A codificação a ser usada (padrão UTF-8)
   */
  public function __construct(string $encoding = 'UTF-8')
  {
    $this->encoding = $encoding;
    $this->output = '';
  }

  /**
   * O destrutor. Redefine as principais variáveis/objetos.
   */
  public function __destruct()
  {
    unset($this->worksheetData);
    unset($this->output);
  }

  /**
   * Cria uma nova planilha e adiciona os dados fornecidos nela.
   * 
   * @param string $title
   *   O título (nome) da planilha
   * @param array $data
   *   Matriz de dados bidimensional com o conteúdo da planilha
   */
  public function addWorksheet(string $title, array $data): void
  {
    $this->worksheetData[] = array(
      'title' => $this->getWorksheetTitle($title),
      'data'  => $data
    );
  }

  /**
   * Grava a pasta de trabalho no arquivo/caminho fornecido como
   * parâmetro. O método verifica se o diretório é gravável e se o
   * arquivo não existe e grava o arquivo.
   *
   * @param string $filename
   *   Nome do arquivo a ser usado para gravação (deve conter tipo mime)
   * @param string $path
   *   Caminho a ser usado para escrever [opcional]
   */
  public function writeWorkbook(
    string $filename,
    string $path = ''
  ): string
  {
    $this->generateWorkbook();
    $filename = $this->getWorkbookTitle($filename);

    if (!$handle = @fopen($path . $filename, 'w+')) {
      throw new Exception(
        sprintf(
          "Não é permitido gravar no arquivo %s",
          $path . $filename
        )
      );
    }

    if (@fwrite($handle, $this->output) === false) {
      throw new Exception(
        sprintf(
          "Erro ao gravar no arquivo %s",
          $path . $filename
        )
      );
    }
    @fclose($handle);

    return sprintf("Arquivo %s escrito", $path . $filename);
  }

  /**
   * Obtém o conteúdo da pasta de trabalho gerada.
   *
   * @return string
   *   O conteúdo da pasta de trabalho
   */
  public function getWorkbook(): string
  {
    $this->generateWorkbook();

    return $this->output;
  }

  /**
   * Gere o conteúdo da pasta de trabalho. Este é o wrapper principal
   * para gerar a pasta de trabalho. Ele invocará a criação de planilhas,
   * linhas e colunas.
   *
   * @return void
   */
  private function generateWorkbook(): void
  {
    $this->output .= stripslashes(
        sprintf(self::header, $this->encoding)
      ) . "\n"
    ;
    
    foreach ($this->worksheetData as $item) {
      $this->generateWorksheet($item);
    }

    $this->output .= self::footer;
  }

  /**
   * Gere a planilha. Quando os dados da planilha superarem os valores
   * mmáximos de linhas permitidos pelo Excel, a matriz é fatiada.
   *
   * @param array $item
   *   Os dados da planilha
   *
   * @return void
   */
  private function generateWorksheet(array $item): void
  {
    $this->output .= sprintf(
      "<Worksheet ss:Name=\"%s\">\n<Table>\n", $item['title']
    );

    if (count($item['data'])) {
      $item['data'] = array_slice($item['data'], 0, 65536);
    }

    foreach ($item['data'] as $value) {
      $this->generateRow($value);
    }

    $this->output .= "</Table>\n</Worksheet>\n";
  }

  /**
   * Gerar uma única linha.
   * 
   * @param array $row
   *   Os dados da linha
   *
   * @return void
   */
  private function generateRow(array $row): void
  {
    $this->output .= "<Row>\n";

    foreach ($row as $column) {
      $this->generateCell($column);
    }

    $this->output .= "</Row>\n";
  }

  /**
   * Gerar o conteúdo de uma única célula.
   * 
   * @param mixed $value
   *   O conteúdo da célula
   *
   * @return void
   */
  private function generateCell($value): void
  {
    // Deteremina o tipo do conteúdo da célula
    $type = 'String';
    if (is_numeric($value)) {
      $type = 'Number';
      if ($value[0] == '0' && strlen($value) > 1 && $value[1] != '.') {
        $type = 'String';
      }
    }

    $value = str_replace(
      '&#039;',
      '&apos;',
      htmlspecialchars($value, ENT_QUOTES)
    );
    $this->output .= sprintf(
      "<Cell><Data ss:Type=\"%s\">%s</Data></Cell>\n",
      $type,
      $value
    );
  }

  /**
   * Obtém o nome do arquivo, removendo os caracteres não permitidos.
   *
   * @param string $filename
   *   Nome do arquivo desejado
   * 
   * @return string
   *   Nome de arquivo corrigido
   */
  private function getWorkbookTitle(string $filename): string
  {
    return preg_replace('/[^aA-zZ0-9\_\-\.]/', '', $filename);
  }

  /**
   * Obtém o nome de título da planilha, eliminando caracteres inválidos
   * no Excel.
   *
   * @param string $title
   *   Título desejado da planilha
   * 
   * @return string
   *   Título da planilha corrigido
   */
  private function getWorksheetTitle(string $title): string
  {
    $title = preg_replace ("/[\\\|:|\/|\?|\*|\[|\]]/", "", $title);
    
    return substr ($title, 0, 31);
  }
}