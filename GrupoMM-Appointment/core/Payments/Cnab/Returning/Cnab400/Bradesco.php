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
 * Uma classe para leitores de arquivos de retorno no padrão CNAB da
 * FEBRABAN gerados pelo Bradesco.
 *
 * O CNAB, sigla para Centro Nacional de Automação Bancária, são
 * diretrizes a serem seguidas para a emissão dos arquivos de remessa e
 * de retorno, tanto por parte das empresas quanto dos bancos.
 * 
 * Ele nada mais é, portanto, do que o layout para registro de cobranças
 * em um molde padrão, o que garante a segurança do pagamento.
 * 
 * Somente com a realização desse registro é possível ao emissor receber
 * o valor referente aos boletos pagos pelos seus clientes. Se um boleto
 * bancário não passar por esse processo, será rejeitado pelo banco.
 *
 * Esta classe permite abstrair a leitura do arquivo que retorna da
 * instituição financeira, permitindo atualizar as informações de
 * pagamentos dos títulos registrados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\Cnab\Returning\Cnab400;

use Core\Payments\Cnab\BilletOccurrence;

final class Bradesco
  extends AbstractReturnFile
{
  // -----[ Dados do banco emissor]-------------------------------------

  /**
   * O código do banco.
   *
   * @var string
   */
  protected $bankCode = '237';

  /**
   * Os códigos de ocorrências para o banco emissor.
   *
   * @var array
   */
  protected $occurrences = [
     2 => "Entrada confirmada",
     3 => "Entrada rejeitada",
     6 => "Liquidação normal",
     7 => "Confirmado exclusão cadastro de pagador débito",
     8 => "Rejeitado pedido exclusão cadastro de pagador débito",
     9 => "Baixa automática via arquivo",
    10 => "Baixado conforme instruções da agência",
    11 => "Em ser - Arquivo de títulos pendentes",
    12 => "Abatimento concedido",
    13 => "Abatimento cancelado",
    14 => "Vencimento alterado",
    15 => "Liquidação em cartório",
    16 => "Título pago em cheque - vinculado",
    17 => "Liquidação após baixa ou título não registrado",
    18 => "Acerto de depositária",
    19 => "Confirmado recebimento da instrução de protesto",
    20 => "Confirmado recebimento da instrução de sustação de protesto",
    21 => "Acerto do controle do participante",
    22 => "Título com pagamento cancelado",
    23 => "Entrada do título em cartório",
    24 => "Entrada rejeitada por CEP irregular",
    25 => "Confirmação recebimento da instrução de protesto falimentar",
    27 => "Baixa rejeitada",
    28 => "Débito de tarifas/custas",
    29 => "Ocorrências do pagador",
    30 => "Alteração de outros dados rejeitados",
    31 => "Confirmado inclusão cadastro pagador",
    32 => "Instrução rejeitada",
    33 => "Confirmado pedido alteração outros dados",
    34 => "Retirado de cartório e manutenção carteira",
    35 => "Cancelamento do agendamento do débito automático",
    37 => "Rejeitado inclusão cadastro pagador",
    38 => "Confirmado alteração pagador",
    39 => "Rejeitado alteração cadastro pagador",
    40 => "Estorno de pagamento",
    55 => "Sustado por decisão judicial",
    66 => "Título baixado por pagamento via Pix",
    68 => "Acerto dos dados do rateio de crédito",
    69 => "Cancelamento dos dados do rateio",
    73 => "Confirmado recebimento pedido de negativação",
    74 => "Confirmado pedido de exclusão de negativação (com ou sem baixa)"
  ];

  /**
   * Os motivos para a ocorrência do registro do título.
   *
   * @var array
   */
  protected $occurrenceReasons = [
    // Ocorrência 02: Entrada Confirmada – Motivos
    2 => [
      '00' => "Ocorrência aceita",
      '01' => "Código do banco inválido",
      '02' => "Pendente de autorização (autorização débito automático)",
      '03' => "Pendente de ação do pagador (autorização débito automático - data vencimento)",
      '04' => "Código do movimento não permitido para a carteira",
      '15' => "Características da cobrança incompatíveis",
      '17' => "Data de vencimento anterior à data de emissão",
      '21' => "Espécie do título inválido",
      '24' => "Data da emissão inválida",
      '27' => "Valor/taxa de juros mora inválido",
      '38' => "Prazo para protesto/negativação inválido",
      '39' => "Pedido para protesto/negativação não permitido para o título",
      '43' => "Prazo para baixa e devolução inválido",
      '45' => "Nome do pagador inválido",
      '46' => "Tipo/num. de inscrição do pagador inválidos",
      '47' => "Endereço do pagador não informado",
      '48' => "CEP inválido",
      '50' => "CEP referente a banco correspondente",
      '53' => "Nº de inscrição do pagador/avalista inválidos (cpf/cnpj)",
      '54' => "Beneficiário final não informado",
      '67' => "Débito automático agendado",
      '68' => "Débito não agendado - Erro nos dados de remessa",
      '69' => "Débito não agendado - Pagador não consta no cadastro de autorizante",
      '70' => "Débito não agendado - Beneficiário não autorizado pelo pagador",
      '71' => "Débito não agendado - Beneficiário não participa da modalidade de débito automático",
      '72' => "Débito não agendado - Código de moeda diferente de R$",
      '73' => "Débito não agendado - Data de vencimento inválida/vencida",
      '75' => "Débito não agendado - Tipo do número de inscrição do pagador debitado inválido",
      '76' => "Pagador eletrônico DDA",
      '86' => "Seu número do documento inválido",
      '87' => "Título baixado por coobrigação e devolvido para carteira",
      '89' => "Email pagador não enviado - Título com débito automático",
      '90' => "Email pagador não enviado - Título de cobrança sem registro",
      'P1' => "Registrado com QR CODE Pix",
      'P2' => "Registrado sem QR CODE Pix",
      'P3' => "Chave Pix inválida",
      'P4' => "Chave Pix sem cadastro no DICT",
      'P5' => "Chave Pix não compatível CNPJ/CPF ou agência/conta informada",
      'P6' => "Identificador (TXID) em duplicidade",
      'P7' => "Identificador (TXID) inválido ou não encontrado",
      'P8' => "Alteração não permitida - QR CODE concluído, removido pelo PSP ou removido pelo usuário recebedor",
    ],
    // Ocorrência 03: Entrada Rejeitada - Motivos
    3 => [
      '00' => "Ocorrência aceita",
      '02' => "Código do registro detalhe inválido",
      '03' => "Código da ocorrência inválida",
      '04' => "Código de ocorrência não permitida para a carteira",
      '05' => "Código de ocorrência não numérico",
      '07' => "Agência/Conta/Digito inválido",
      '08' => "Nosso número inválido",
      '09' => "Nosso número duplicado",
      '10' => "Carteira inválida",
      '13' => "Identificação da emissão do bloqueto inválida",
      '16' => "Data de vencimento inválida",
      '18' => "Vencimento fora do prazo de operação",
      '20' => "Valor do título inválido",
      '21' => "Espécie do título inválida",
      '22' => "Espécie não permitida para a carteira",
      '24' => "Data de emissão inválida",
      '27' => "Valor/taxa de juros mora inválido",
      '28' => "Código do desconto inválido",
      '29' => "Valor do desconto maior ou igual ao valor do título",
      '32' => "Valor do IOF inválido",
      '34' => "Valor do abatimento maior ou igual ao valor do título",
      '38' => "Prazo para protesto/negativação inválido",
      '39' => "Pedido de protesto/negativação não permitida para o título",
      '44' => "Código da moeda inválido",
      '45' => "Nome do pagador não informado",
      '46' => "Tipo/número de inscrição do pagador inválidos",
      '47' => "Endereço do pagador não informado",
      '48' => "CEP Inválido",
      '49' => "CEP sem praça de cobrança",
      '50' => "CEP irregular - Banco correspondente",
      '53' => "Tipo/número de inscrição do beneficiário final inválido",
      '54' => "Sacador/avalista (beneficiário final) não informado",
      '59' => "Valor/percentual da multa inválido",
      '63' => "Entrada para título já cadastrado",
      '65' => "Limite excedido",
      '66' => "Número autorização inexistente",
      '68' => "Débito não agendado - Erro nos dados de remessa",
      '69' => "Débito não agendado - Pagador não consta no cadastro de autorizante",
      '70' => "Débito não agendado - Beneficiário não autorizado pelo pagador",
      '71' => "Débito não agendado - Beneficiário não participa do débito automático",
      '72' => "Débito não agendado - Código de moeda diferente de R$",
      '73' => "Débito não agendado - Data de vencimento inválida/cadastro vencido",
      '74' => "Débito não agendado - Conforme seu pedido, título não registrado",
      '75' => "Débito não agendado – Tipo de número de inscrição do debitado inválido",
      '79' => "Data de juros de mora inválida",
      '80' => "Data do desconto inválida",
      '86' => "Seu número inválido",
      'A3' => "Beneficiário final/sacador/pagador devem ser iguais",
      'A6' => "Esp. BDP/depósito e aporte, não aceita pagamento parcial"
    ],
    // Ocorrência 06: Liquidação - Motivo
    6 => [
      '00' => "Crédito disponível",
      '15' => "Crédito indisponível",
      '18' => "Pagamento parcial",
      '42' => "Rateio não efetuado"
    ],
    // Ocorrência 07: Confirmado exclusão cadastro de pagador débito - Motivo
    7 => [
      'A0' => "Cadastro excluído pelo beneficiário",
      'A1' => "Cadastro excluído pelo pagador"
    ],
    // Ocorrência 08: Rejeitado pedido exclusão cadastro de pagador débito - Motivo
    8 => [
      'C0' => "Informações do tipo 6 inválidas",
      'B9' => "Cadastro pagador não localizado"
    ],
    // Ocorrência 09: Baixado automaticamente via arquivo - Motivo
    9 => [
      '00' => "Ocorrência aceita",
      '10' => "Baixa comandada pelo cliente",
      '18' => "Pagador não aceitou o débito (Autorização débito automático)",
      '19' => "Pendente de ação do pagador (Autorização débito automático)"
    ],
    // Ocorrência 10: Baixado conforme instruções da agência - Motivo
    10 => [
      '00' => "Baixado conforme instruções da agência",
      '14' => "Título protestado",
      '16' => "Título baixado pelo banco por decurso de prazo",
      '20' => "Titulo baixado e transferido para desconto"
    ],
    // Ocorrência 15: Liquidação em cartório - Motivo
    15 => [
      '00' => "Crédito disponível",
      '15' => "Crédito indisponível"
    ],
    // Ocorrência 17: Liquidação após baixa ou título não registrado - Motivo
    17 => [
      '00' => "Crédito disponível",
      '15' => "Crédito indisponível"
    ],
    // Ocorrência 24: Entrada Rejeitada por CEP Irregular - Motivo
    24 => [
      '00' => "Ocorrência aceita",
      '48' => "CEP inválido",
      '49' => "CEP sem praça de cobrança"
    ],
    // Ocorrência 27: Baixa Rejeitada - Motivos
    27 => [
      '00' => "Ocorrência aceita",
      '02' => "Código do registro detalhe inválido",
      '04' => "Código de ocorrência não permitido para a carteira",
      '07' => "Agência/conta/dígito inválidos",
      '08' => "Nosso número inválido",
      '09' => "Nosso número duplicado",
      '10' => "Carteira inválida",
      '15' => "Carteira/agência/conta/nosso número inválidos",
      '16' => "Data vencimento inválida",
      '18' => "Vencimento fora do prazo de operação",
      '20' => "Valor título inválido",
      '40' => "Título com ordem de protesto emitido",
      '42' => "Código para baixa/devolução inválido",
      '45' => "Nome do sacado não informado ou inválido",
      '46' => "Tipo/número de inscrição do sacado inválido",
      '47' => "Endereço do sacado não informado",
      '48' => "CEP inválido",
      '60' => "Movimento para título não cadastrado",
      '77' => "Transferência para desconto não permitido para a carteira",
      '85' => "Título com pagamento vinculado",
      '86' => "Seu número inválido"
    ],
    // Ocorrência 28: Débito de Tarifas/Custas - Motivos
    28 => [
      '00' => "Débito de tarifas/custas",
      '02' => "Tarifa de permanência título cadastrado",
      '03' => "Tarifa de sustação/exclusão negativação",
      '04' => "Tarifa de protesto/inclusão negativação",
      '08' => "Custas de protesto",
      '12' => "Tarifa de registro",
      '13' => "Tarifa título pago no Bradesco",
      '14' => "Tarifa título pago compensação",
      '15' => "Tarifa título baixado não pago",
      '16' => "Tarifa alteração de vencimento",
      '17' => "Tarifa concessão abatimento",
      '18' => "Tarifa cancelamento de abatimento",
      '19' => "Tarifa concessão desconto",
      '20' => "Tarifa cancelamento desconto",
      '21' => "Tarifa título pago CICS",
      '22' => "Tarifa título pago internet",
      '23' => "Tarifa título pago term. gerencial serviços",
      '24' => "Tarifa título pago Pag-contas",
      '25' => "Tarifa título pago Fone Fácil",
      '26' => "Tarifa título déb. postagem",
      '28' => "Tarifa título pago BDN",
      '29' => "Tarifa título pago term. multi função",
      '32' => "Tarifa título pago Pagfor",
      '33' => "Tarifa reg/pgto - Guichê caixa",
      '34' => "Tarifa título pago retaguarda",
      '35' => "Tarifa título pago subcentro",
      '36' => "Tarifa título pago cartão de crédito",
      '37' => "Tarifa título pago compensação eletrônica",
      '38' => "Tarifa título baixa pago cartório",
      '39' => "Tarifa título baixado acerto banco",
      '40' => "Baixa registro em duplicidade",
      '41' => "Tarifa título baixado decurso prazo",
      '42' => "Tarifa título baixado judicialmente",
      '43' => "Tarifa título baixado via remessa",
      '44' => "Tarifa título baixado rastreamento",
      '45' => "Tarifa título baixado conf. pedido",
      '46' => "Tarifa título baixado protestado",
      '47' => "Tarifa título baixado p/ devolução",
      '48' => "Tarifa título baixado franco pagto",
      '49' => "Tarifa título baixado sust/ret/cartório",
      '50' => "Tarifa título baixado sus/sem/rem/cartório",
      '51' => "Tarifa título transferido desconto",
      '54' => "Tarifa baixa por contabilidade",
      '55' => "Tarifa tentativa cons déb aut",
      '56' => "Tarifa crédito on-line",
      '57' => "Tarifa reg/pagto bradesco expresso",
      '58' => "Tarifa emissão papeleta",
      '78' => "Tarifa cadastro cartela instrução permanente",
      '80' => "Tarifa parcial pagamento compensação",
      '81' => "Tarifa reapresentação automática título",
      '82' => "Tarifa registro título déb. automático",
      '83' => "Tarifa rateio de crédito",
      '89' => "Tarifa parcial pagamento bradesco",
      '96' => "Tarifa registro/pagto outras mídias",
      '97' => "Tarifa registro/pagto - Net Empresa",
      '98' => "Tarifa título pago vencido",
      '99' => "Tarifa título baixado por decurso prazo"
    ],
    // Ocorrência 29: Ocorrência do pagador - Motivos
    29 => [
      '78' => "Pagador alega que faturamento é indevido",
      '95' => "Pagador aceita/reconhece faturamento"
    ],
    // Ocorrência 30: Alteração de outros dados rejeitados - Motivos
    30 => [
      '00' => "Ocorrência aceita",
      '01' => "Código do banco inválido",
      '04' => "Código de ocorrência não permitido para a carteira",
      '05' => "Código da ocorrência não numérico",
      '08' => "Nosso número inválido",
      '15' => "Característica da cobrança incompatível",
      '16' => "Data de vencimento inválida",
      '17' => "Data de vencimento anterior à data de emissão",
      '18' => "Vencimento fora do prazo de operação",
      '20' => "Valor título inválido",
      '21' => "Espécie título inválida",
      '22' => "Espécie não permitida para a carteira",
      '23' => "Tipo pagamento não contratado",
      '24' => "Data de emissão inválida",
      '26' => "Código de juros de mora inválido",
      '27' => "Valor/taxa de juros de mora inválido",
      '28' => "Código de desconto inválido",
      '29' => "Valor do desconto maior/igual ao valor do título",
      '30' => "Desconto a conceder não confere",
      '31' => "Concessão de desconto já existente (desconto anterior)",
      '32' => "Valor do IOF inválido",
      '33' => "Valor do abatimento inválido",
      '34' => "Valor do abatimento maior/igual ao valor do título",
      '36' => "Concessão abatimento",
      '38' => "Prazo para protesto/negativação inválido",
      '39' => "Pedido para protesto/negativação não permitido para o título",
      '40' => "Título com ordem/pedido de protesto/negativação emitido",
      '42' => "Código para baixa/devolução inválido",
      '43' => "Prazo para baixa/devolução inválido",
      '46' => "Tipo/número de inscrição do pagador inválidos",
      '48' => "Cep inválido",
      '53' => "Tipo/número de inscrição do pagador/avalista inválidos",
      '54' => "Pagador/avalista não informado",
      '57' => "Código da multa inválido",
      '58' => "Data da multa inválida",
      '60' => "Movimento para título não cadastrado",
      '79' => "Data de juros de mora inválida",
      '80' => "Data do desconto inválida",
      '85' => "Título com pagamento vinculado.",
      '88' => "E-mail pagador não lido no prazo 5 dias",
      '91' => "E-mail pagador não recebido",
      'C0' => "Informações do tipo 6 inválidas",
      'C1' => "Informações do tipo 6 divergentes do cadastro"
    ],
    // Ocorrência 32: Instrução rejeitada - Motivos
    32 => [
      '00' => "Ocorrência aceita",
      '01' => "Código do banco inválido",
      '02' => "Código registro detalhe inválido",
      '04' => "Código de ocorrência não permitido para a carteira",
      '05' => "Código de ocorrência não numérico",
      '06' => "Espécie BDP, não aceita pagamento parcial",
      '07' => "Agência/conta/dígito inválidos",
      '08' => "Nosso número inválido",
      '10' => "Carteira inválida",
      '15' => "Características da cobrança incompatíveis",
      '16' => "Data de vencimento inválida",
      '17' => "Data de vencimento anterior à data de emissão",
      '18' => "Vencimento fora do prazo de operação",
      '20' => "Valor do título inválido",
      '21' => "Espécie do título inválida",
      '22' => "Espécie não permitida para a carteira",
      '23' => "Tipo pagamento não contratado",
      '24' => "Data de emissão inválida",
      '26' => "Código juros mora inválido",
      '27' => "Valor/taxa juros mira inválido",
      '28' => "Código de desconto inválido",
      '29' => "Valor do desconto maior/igual ao valor do título",
      '30' => "Desconto a conceder não confere",
      '31' => "Concessão de desconto - já existe desconto anterior",
      '33' => "Valor do abatimento inválido",
      '34' => "Valor do abatimento maior/igual ao valor do título",
      '36' => "Concessão abatimento - já existe abatimento anterior",
      '38' => "Prazo para protesto/negativação inválido",
      '39' => "Pedido para protesto/negativação não permitido para o título",
      '40' => "Título com ordem/pedido de protesto/negativação emitido",
      '41' => "Pedido de sustação/excl p/ título sem instrução de protesto/negativação",
      '45' => "Nome do pagador não informado",
      '46' => "Tipo/número de inscrição do pagador inválidos",
      '47' => "Endereço do pagador não informado",
      '48' => "CEP inválido",
      '50' => "CEP referente a um banco correspondente",
      '52' => "Unidade da federação inválida",
      '53' => "Tipo de inscrição do pagador avalista inválidos",
      '60' => "Movimento para título não cadastrado",
      '65' => "Limite excedido",
      '66' => "Número autorização inexistente",
      '85' => "Título com pagamento vinculado",
      '86' => "Seu número inválido",
      '94' => "Título cessão fiduciária - instrução não liberada pela agência",
      '97' => "Instrução não permitida título negativado",
      '98' => "Inclusão bloqueada face à determinação judicial",
      '99' => "Telefone beneficiário não informado/inconsistente"
    ],
    // Ocorrência 35: Desagendamento do débito automático - Motivos
    35 => [
      '81' => "Tentativas esgotadas, baixado",
      '82' => "Tentativas esgotadas, pendente",
      '83' => "Cancelado pelo pagador e mantido pendente, conforme negociação",
      '84' => "Cancelado pelo pagador e baixado, conforme negociação"
    ],
    // Ocorrência 37: Rejeitado inclusão cadastro pagador - Motivos
    37 => [
      'C0' => "Informações do tipo 6 inválidas",
      'C1' => "Informações do tipo 6 divergentes do cadastro"
    ],
    // Ocorrência 39: Rejeitado alteração cadastro pagador - Motivos
    39 => [
      'C0' => "Informações do tipo 6 inválidas",
      'C1' => "Informações do tipo 6 divergentes do cadastro"
    ]
  ];

  /**
   * Executa a inicialização dos contadores antes do início do
   * processamento.
   *
   * @return void
   */
  protected function init(): void
  {
    $this->totalizers = [
      'paid' => 0,
      'entries' => 0,
      'retired' => 0,
      'protested' => 0,
      'unprotested' => 0,
      'negativated' => 0,
      'unnegativated' => 0,
      'errors' => 0,
      'changed' => 0,
    ];
  }

  /**
   * Processa o registro do cabeçalho, fazendo a interpretação dos
   * campos que compõe o mesmo.
   * 
   * @param array $header
   *   A matriz com o conteúdo do cabeçalho.
   *
   * @return boolean
   */
  protected function processHeader(array $header)
  {
    $this->getHeader()
      ->setOperationCode($this->cut(2, 2, $header))
      ->setOperation($this->cut(3, 9, $header))
      ->setServiceCode($this->cut(10, 11, $header))
      ->setService($this->cut(12, 26, $header))
      ->setClientCode($this->cut(27, 46, $header))
      ->setDate($this->cut(95, 100, $header))
    ;

    return true;
  }

  /**
   * Processa o registro da transação, fazendo a interpretação dos
   * campos que compõe o mesmo.
   * 
   * @param array $transaction
   *   A matriz com o conteúdo da transação.
   *
   * @return boolean
   */
  protected function processTransaction(array $transaction): bool
  {
    if ($this->count() == 1) {
      // Definimos a informação da agência e conta do emissor
      $this->getHeader()
        ->setAgencyNumber($this->cut(25, 29, $transaction))
        ->setAccountNumber($this->cut(30, 36, $transaction))
        ->setDACOfAccountNumber($this->cut(37, 37, $transaction))
      ;
    }

    // Obtemos a transação corrente
    $currentTransaction = $this->currentTransaction();

    $currentTransaction
      ->setWallet($this->cut(108, 108, $transaction))
      ->setBankIdentificationNumber($this->cut(71, 82, $transaction))
      ->setDocumentNumber($this->cut(117, 126, $transaction))
      ->setControlNumber($this->cut(38, 62, $transaction))
      ->setOccurrenceCode((int) $this->cut(109, 110, $transaction))
      ->setOccurrenceDescription($this->getOccurrenceDescription($currentTransaction->getOccurrenceCode()))
      ->setOccurrenceDate($this->cut(111, 116, $transaction))
      ->setDueDate($this->cut(147, 152, $transaction))
      ->setCreditDate($this->cut(296, 301, $transaction))
      ->setDocumentValue($this->toFloat($this->cut(153, 165, $transaction)))
      ->setTariffValue($this->toFloat($this->cut(176, 188, $transaction)))
      ->setIOFValue($this->toFloat($this->cut(215, 227, $transaction)))
      ->setAbatementValue($this->toFloat($this->cut(228, 240, $transaction)))
      ->setDiscountValue($this->toFloat($this->cut(241, 253, $transaction)))
      ->setPaidValue($this->toFloat($this->cut(254, 266, $transaction)))
      ->setLatePaymentInterest($this->toFloat($this->cut(267, 279, $transaction)))
      ->setFineValue($this->toFloat($this->cut(280, 292, $transaction)))
    ;

    // Obtemos os motivos para a ocorrência do título (se existentes)
    $reasonsForOccurrence = str_split(sprintf('%08s', $this->cut(319, 328, $transaction)), 2) + array_fill(0, 5, '');

    // Transformamos os códigos de ocorrência para um padrão que
    // independe da instituição financeira
    if ($currentTransaction->hasOccurrence( 6, 15, 17, 66 )) {
      // TODO: 17: Liquidação após Baixa ou Título não Registrado precisa
      //           verificar motivo nas posições 319 a 328
      $this->totalizers['paid']++;
      $currentTransaction->setOccurrenceType((int) BilletOccurrence::LIQUIDATED);

      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      if ( $occurrenceCode === 17 ) {
        // Obtém os motivos da ocorrência
        $reasons = [];
        $occurrenceCode = $currentTransaction->getOccurrenceCode();
        $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
        $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
        $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
        $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
        $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

        // Armazena os motivos
        $currentTransaction->setReasons($reasons);
      }

      if ( $occurrenceCode === 66 ) {
        $reasons = [];
        $reasons[] = 'Título pago com Pix';
      }
    } elseif ($currentTransaction->hasOccurrence( 9, 10 )) {
      // TODO: A baixa de títulos deve conter o motivo da baixa (se por
      //       solicitação da agência e/ou por solicitação por arquivo de
      //       remessa) com o texto explicativo.
      $this->totalizers['retired']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::DROPPED);

      // Obtém os motivos da ocorrência
      $reasons = [];
      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

      // Armazena os motivos
      $currentTransaction->setReasons($reasons);
    } elseif ($currentTransaction->hasOccurrence( 2 )) {
      $this->totalizers['entries']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::ENTRY);

      // Obtém os motivos da ocorrência
      $reasons = [];
      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

      // Armazena os motivos
      $currentTransaction->setReasons($reasons);
    } elseif ($currentTransaction->hasOccurrence( 7, 14, 33 )) {
      $this->totalizers['changed']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::CHANGE);

      // Obtém os motivos da ocorrência
      $reasons = [];
      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      switch ($occurrenceCode) {
        case 7:
          // Obtém os motivos da ocorrência
          $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
          $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
          $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
          $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
          $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

          break;
        case 14:
          $reasons[] = 'Vencimento alterado';

          break;
        case 33:
          $reasons[] = 'Confirmação pedido alteração outros dados';

          break;
        default:
          $reasons[] = '';

          break;
      }

      // Armazena os motivos
      $currentTransaction->setReasons($reasons);
    } elseif ($currentTransaction->hasOccurrence( 23 )) {
      // Entrada do título em cartório
      $this->totalizers['protested']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::PROTESTED);
    } elseif ($currentTransaction->hasOccurrence( 34 )) {
      // Retirado de cartório e manutenção em carteira
      $this->totalizers['unprotested']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::UNPROTESTED);
    } elseif ($currentTransaction->hasOccurrence( 73 )) {
      // Confirmado recebimento pedido de negativação
      $this->totalizers['negativated']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::CREDIT_BLOCKED);
    } elseif ($currentTransaction->hasOccurrence( 74 )) {
      // Confirmação pedido de exclusão de negativação (com ou sem baixa)
      $this->totalizers['unnegativated']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::CREDIT_UNBLOCKED);
    } elseif ($currentTransaction->hasOccurrence( 12 )) {
      // Abatimento concedido
      $this->totalizers['changed']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::ABATEMENT);
    } elseif ($currentTransaction->hasOccurrence( 13 )) {
      // Abatimento cancelado
      $this->totalizers['changed']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::UNABATEMENT);
    } elseif ($currentTransaction->hasOccurrence( 28 )) {
      $currentTransaction->setOccurrenceType(BilletOccurrence::TARIFF);

      // Obtém os motivos da ocorrência
      $reasons = [];
      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

      // Armazena os motivos
      $currentTransaction->setReasons($reasons);
    } elseif ($currentTransaction->hasOccurrence( 3, 8, 24, 27, 29, 30, 32, 37, 39 )) {
      $this->totalizers['errors']++;
      $currentTransaction->setOccurrenceType(BilletOccurrence::ERROR);

      // Obtém os motivos da ocorrência
      $reasons = [];
      $occurrenceCode = $currentTransaction->getOccurrenceCode();
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[0]);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[1], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[2], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[3], true);
      $reasons[] = $this->getReason($occurrenceCode, $reasonsForOccurrence[4], true);

      if ( $occurrenceCode === 3 ) {
        $currentTransaction->setRejectionReason($reasons[0]);
      }

      // Armazena os motivos
      $currentTransaction->setReasons($reasons);
    } else {
      // TODO: Melhorar a análise destas ocorrências
      //       11..Em Ser - Arquivo de Títulos Pendentes
      //       16..Título Pago em Cheque - Vinculado
      //       18..Acerto de Depositária
      //       19..Confirmação Receb. Inst. de Protesto (verificar motivo pos.295 a 295)
      //       20..Confirmação Recebimento Instrução Sustação de Protesto
      //       21..Acerto do Controle do Participante
      //       22..Título com Pagamento Cancelado
      //       25..Confirmação Receb.Inst.de Protesto Falimentar (verificar pos.295 a 295)
      //       31..Confirmado Inclusão Cadastro Pagador
      //       35..Cancelamento do Agendamento do Débito Automático (verificar motivos pos. 319 a 328)
      //       38..Confirmado Alteração Pagador
      //       40..Estorno de Pagamento
      //       55..Sustado Judicial
      //       68..Acerto dos Dados do Rateio de Crédito (verificar motivo posição de status do registro Tipo 3)
      //       69..Cancelamento de Rateio (verificar motivo posição de status do registro Tipo 3)
      $currentTransaction->setOccurrenceType(BilletOccurrence::OTHERS);
    }

    return true;
  }

  /**
   * Processa o registro de complemento da transação, fazendo a
   * interpretação dos campos que compõe o mesmo.
   * 
   * @param array $transaction
   *   A matriz com o conteúdo do complemento da transação.
   * 
   * @return boolean
   */
  protected function processTransactionComplement(array $transaction): bool
  {
    // Obtemos a transação corrente
    $currentTransaction = $this->currentTransaction();

    // Obtemos as informações do QR Code
    $spiUrl = $this->cut(29, 105, $transaction);
    $txId = $this->cut(106, 140, $transaction);

    $currentTransaction
      ->setSpiUrl($spiUrl)
      ->setTxId($txId)
    ;
    
    return true;
  }

  /**
   * Processa o registro de fechamento, fazendo a interpretação
   * dos campos que compõe o mesmo.
   * 
   * @param array $trailer
   *   A matriz com o conteúdo do fechamento.
   *
   * @return boolean
   */
  protected function processTrailer(array $trailer)
  {
    $this->getTrailer()
      ->setNumberOfBonds( (int) $this->cut(18, 25, $trailer) )
      ->setValueOfBonds( $this->toFloat($this->cut(26, 39, $trailer)) )
      ->setAmountOfErrors( (int) $this->totalizers['errors'] )
      ->setAmountOfEntries( (int) $this->totalizers['entries'] )
      ->setAmountOfPaid( (int) $this->totalizers['paid'] )
      ->setAmountOfRetired( (int) $this->totalizers['retired'] )
      ->setAmountOfChanges( (int) $this->totalizers['changed'] )
    ;

    return true;
  }
}