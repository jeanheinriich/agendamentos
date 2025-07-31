// Um formatador (render) de coluna para exibir o ícone de expiração
var expiresFormatter = function( title, activeStr, inactiveStr ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      if ( value ) {
        // Retornamos um ícone indicativo de expiração ativo
        return '<span data-position="left center"' +
                     'data-blue data-inverted ' +
                     'data-tooltip="' + title.replace('%s', activeStr) + '">' +
                 '<i class="calendar times darkorange icon">' +
                 '</i>' +
               '</span>';
      } else {
        // Retornamos um ícone indicativo de expiração inativo
        return '<span data-position="left center"' +
                     'data-blue data-inverted ' +
                     'data-tooltip="' + title.replace('%s', inactiveStr) + '">' +
                 '<i class="calendar times lightgrey icon">' +
                 '</i>' +
               '</span>';
      }
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
