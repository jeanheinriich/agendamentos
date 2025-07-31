// Um formatador (render) de coluna para exibir uma cor
var colorFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Exibimos um quadrado preenchido com a cor
    if (value) {
      return '<a class="ui ' + value + ' empty square label"></a>';
    } else {
      return '<a class="ui empty square label"></a>';
    }
  }
  
  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
