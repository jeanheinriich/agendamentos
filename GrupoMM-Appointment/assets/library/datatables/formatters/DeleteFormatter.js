// Um formatador (render) de coluna para exibir o ícone de remoção
var deleteFormatter = function( tooltip ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      var color = 'lightgrey';
      var enableToolTip = false;
      if ( isNaN(value) ) {
        // Sendo um texto, coloca em vermelho se tiver conteúdo
        if ( value.length > 0 ) {
          color = 'darkred';
          enableToolTip = true;
        }
      } else {
        // Sendo um número, coloca em vermelho se o valor for maior do
        // que zero
        if ( parseInt(value) > 0 ) {
          color = 'darkred';
          enableToolTip = true;
        }
      }

      // Retornamos um ícone de exclusão
      if (enableToolTip) {
        return '<span data-position="left center"' +
                     'data-blue data-inverted ' +
                     'data-tooltip="' + tooltip + '">' +
                 '<i class="remove ' + color + ' icon">' +
                 '</i>' +
               '</span>';
      } else {
        return '<i class="remove ' + color + ' icon"></i>';
      }
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
