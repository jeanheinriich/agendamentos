// Um formatador (render) de coluna para exibir o ícone indicativo de
// que o conteúdo está vinculado a outro
var attachedFormatter = function( linked, unlinked, field ) {
  return function ( value, type, row, meta ) {
    // Verificamos se formato ainda não foi implementado
    if (!String.prototype.format) {
      // Acrescentamos a formatação
      String.prototype.format = function() {
        var args = arguments;
        return this.replace(/{(\d+)}/g, function(match, number) { 
          return typeof args[number] != 'undefined'
            ? args[number]
            : match
          ;
        });
      };
    }

    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      if (value) {
        var
          title = (field === undefined)
            ? linked
            : linked.format(row[field]),
          linkedTo = (field === undefined)
            ? ''
            : row[field]
        ;

        // Retornamos um ícone de que está vinculado
        return ''
          + '<span data-position="left center"'
          +       'data-blue data-inverted '
          +       'data-tooltip="' + title + '">'
          +   '<i class="linkify linked icon">'
          +   '</i>'
          +   linkedTo
          + '</span>'
        ;
      } else {
        // Retornamos um ícone de que está livre
        return ''
          + '<span data-position="left center"'
          +       'data-blue data-inverted '
          +       'data-tooltip="' + unlinked + '">'
          +   '<i class="unlink unlinked icon">'
          +   '</i>'
          + '</span>'
        ;
      }
    }
    
    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};