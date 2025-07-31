// Um formatador (render) de coluna para exibir o texto indicativo de
// veículo Ativo ou Inativo
var activeFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (value) {
      // Retornamos um texto indicando que está inativo
      return '<span class="status darkred">' +
               'Inativo' +
             '</span>';
    } else {
      // Retornamos um texto indicando que está ativo
      return '<span class="status darkgreen">' +
               'Ativo' +
             '</span>';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
