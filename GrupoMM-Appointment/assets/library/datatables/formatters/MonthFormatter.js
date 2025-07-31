// Um formatador (render) de coluna para exibir o nome do mês
var monthFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Modificamos o valor para o nome do mês
    var monthName = [
      'Inválido', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio',
      'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro',
      'Dezembro'
    ];

    if ((0 <= value) && (value <= 12)) {
      value = monthName[ value ];
    } else {
      value = monthName[ 0 ];
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
