// Um formatador de coluna para exibir um ícone na barra de título
var iconTitleFormatter = function( icon, color, tooltip ) {
  if (tooltip) {
    return ''
      + '<span data-position="left center"'
      +       'data-blue data-inverted '
      +       'data-tooltip="' + tooltip + '">'
      +   '<i class="' + icon + ' ' + color + ' icon">'
      +   '</i>'
      + '</span>'
    ;
  } else {
    return '<i class="' + icon + ' ' + color + ' icon"></i>';
  }
};

// Um formatador (render) de coluna para exibir um ícone
var iconFormatter = function( icon, color, tooltip ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      // Retornamos um ícone
      if (tooltip) {
        return ''
          + '<span data-position="left center"'
          +       'data-blue data-inverted '
          +       'data-tooltip="' + tooltip + '">'
          +   '<i class="' + icon + ' ' + color + ' icon">'
          +   '</i>'
          + '</span>'
        ;
      } else {
        return '<i class="' + icon + ' ' + color + ' icon"></i>';
      }
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
