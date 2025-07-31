var isMobile = {
  Android: function() {
    return navigator.userAgent.match(/Android/i);
  },
  BlackBerry: function() {
    return navigator.userAgent.match(/BlackBerry/i);
  },
  iOS: function() {
    return navigator.userAgent.match(/iPhone|iPad|iPod/i);
  },
  Opera: function() {
    return navigator.userAgent.match(/Opera Mini/i);
  },
  Windows: function() {
    return navigator.userAgent.match(/IEMobile/i);
  },
  any: function() {
    return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
  }
};

$(function () {
  // ===============================================[ Componentes ]=====
  // Ativa as funcionalidades dos componentes
  // -------------------------------------------------------------------
  
  // ------------------------------------------------[ Menus Mobile ]---
  $('.trigger').click(function() {
    $('#mainMenu').toggleClass('hide');
  });

  // ----------------------------------------------[ Menus Dropdown ]---
  $('.ui.menu .ui.dropdown').dropdown({
    on: 'hover'
  });

  // --------------------------------------[ Mensagens Instantâneas ]---
  $('.flash.message .close').on('click', function () {
    $(this).closest('.flash.message').hide('500');
  });

  // ----------------------------------------------------[ Whatsapp ]---
  let hiddenWhatsapp = setTimeout(function() {
    $(".whatsapp.floating.button span.text")
      .removeClass('initial')
    ;
  }, 5000);
  
  $(".whatsapp.floating.button").on("click", function()
  {
    var
      text = $(".whatsapp.floating.button").attr("data-message"),
      phone = $(".whatsapp.floating.button").attr("data-number"),
      message = encodeURIComponent(text),
      url = ''
    ;
    if( isMobile.any() ) {
      // Dispositivo móvel
      url = 'whatsapp://send?phone=' + phone + '&text=' + message;

    } else {
      // Desktop
      url = 'https://api.whatsapp.com/send?phone=' + phone + '&text=' + message;
    }
    window.open(url);
  });

  // ---------------------------------------------------[ Insurance ]---
  let hiddenInsurance = setTimeout(function() {
    $(".insurance.floating.button span.text")
      .removeClass('initial')
    ;
  }, 5000);
  $(".insurance.floating.button").on("click", function()
  {
    var
      url = "quotation"
    ;
    window.open(url,"_self")
  });

  // --------------------------------------------------------[ LGPD ]---
  var
    consent = getCookie('LGPD-Consent')
  ;
  if (consent !== 'consent') {
    $(".lgpd.nag button.accept").on("click", function()
    {
      console.log('Escondendo nag');
      setCookie('LGPD-Consent', 'consent');

      $('.lgpd.nag')
        .fadeOut('slow', function() {
          $(this)
            .removeClass('show')
          ;
        })
      ;
    });

    $('.lgpd.nag')
      .addClass('show')
    ;
  }
});
