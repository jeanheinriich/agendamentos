// Um formatador (render) de coluna para exibir o ícone de habilitação
var enableFormatter = function( title ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      if (value) {
        // Retornamos um ícone de que o atributo está habilitado
        return '<span data-position="left center"' +
                     'data-blue data-inverted ' +
                     'data-tooltip="' + title + '">' +
                 '<i class="checkmark darkgreen icon">' +
                 '</i>' +
               '</span>';
      } else {
        // Retornamos um ícone de que o atributo está desabilitado
        return '<i class="icon"></i>';
      }
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
